<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractor;
use Sirix\Mezzio\Routing\Attributes\Extractor\ClassEligibilityValidator;
use Sirix\Mezzio\Routing\Attributes\Extractor\MethodSignatureValidator;
use Sirix\Mezzio\Routing\Attributes\Extractor\ModifierCollectionRegistry;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteAttributeReader;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteDataNormalizer;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteDefinitionBuilder;
use Sirix\Mezzio\Routing\Attributes\RouteDefinition;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\AggregatingModifierHandler;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\DuplicateSpecificationUniqueMiddlewareHandler;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\ModifierHandler;

final class RouteDefinitionBuilderModifierTest extends TestCase
{
    public function testCollectsMiddlewareAndDefaultsFromModifierAttributes(): void
    {
        $attributeReader = new RouteAttributeReader();
        $builder         = new RouteDefinitionBuilder(
            $attributeReader,
            new MethodSignatureValidator(),
            new RouteDataNormalizer()
        );

        /** @var ReflectionClass<object> $reflection */
        $reflection  = new ReflectionClass(ModifierHandler::class);
        $definitions = $builder->buildForClass($reflection, ModifierHandler::class);

        self::assertCount(1, $definitions);
        $route = $definitions[0];

        self::assertInstanceOf(RouteDefinition::class, $route);
        self::assertSame('/modifier', $route->path);
        self::assertSame(['modifier.middleware'], $route->middlewareServices);
        self::assertSame([
            'modifier_key' => 'modifier_value',
        ], $route->defaults);
    }

    public function testPreservesUniqueMiddlewareIdentityForLegacyClassModifierTuple(): void
    {
        $attributeReader = new RouteAttributeReader();
        $builder         = new RouteDefinitionBuilder(
            $attributeReader,
            new MethodSignatureValidator(),
            new RouteDataNormalizer()
        );

        /** @var ReflectionClass<object> $reflection */
        $reflection       = new ReflectionClass(AggregatingModifierHandler::class);
        $classModifiers   = $builder->collectClassModifiers($reflection, AggregatingModifierHandler::class);
        $method           = $reflection->getMethod('index');
        $methodAttributes = $attributeReader->forReflection($method);
        $definitions      = $builder->buildForMethodWithAttributes(
            $method,
            AggregatingModifierHandler::class,
            [],
            $methodAttributes,
            $classModifiers
        );

        self::assertSame([
            'class.middleware',
            'mapper.middleware',
            'method.middleware',
        ], $definitions[0]->middlewareServices);
        self::assertSame([
            'mappings' => ['query', 'body'],
        ], $definitions[0]->defaults);
    }

    public function testRestoresUniqueMiddlewareIdentityForValueEquivalentLegacySpecificationTuple(): void
    {
        $attributeReader = new RouteAttributeReader();
        $builder         = new RouteDefinitionBuilder(
            $attributeReader,
            new MethodSignatureValidator(),
            new RouteDataNormalizer()
        );

        /** @var ReflectionClass<object> $reflection */
        $reflection           = new ReflectionClass(DuplicateSpecificationUniqueMiddlewareHandler::class);
        $classModifiers       = $builder->collectClassModifiers($reflection, DuplicateSpecificationUniqueMiddlewareHandler::class);
        $classModifiers[0][0] = new MiddlewareSpecification('mapper', 'mapper.factory', [
            'mode' => 'strict',
        ]);
        $definitions = $builder->buildForMethodWithAttributes(
            $reflection->getMethod('index'),
            DuplicateSpecificationUniqueMiddlewareHandler::class,
            [],
            $attributeReader->forReflection($reflection->getMethod('index')),
            $classModifiers
        );

        self::assertCount(1, $definitions[0]->middlewareServices);
    }

    public function testDoesNotRestoreUniqueMiddlewareIdentityForDivergentLegacySpecificationTuple(): void
    {
        $attributeReader = new RouteAttributeReader();
        $builder         = new RouteDefinitionBuilder(
            $attributeReader,
            new MethodSignatureValidator(),
            new RouteDataNormalizer()
        );

        /** @var ReflectionClass<object> $reflection */
        $reflection           = new ReflectionClass(DuplicateSpecificationUniqueMiddlewareHandler::class);
        $classModifiers       = $builder->collectClassModifiers($reflection, DuplicateSpecificationUniqueMiddlewareHandler::class);
        $classModifiers[0][0] = new MiddlewareSpecification('mapper', 'mapper.factory', [
            'mode' => 'lenient',
        ]);
        $definitions = $builder->buildForMethodWithAttributes(
            $reflection->getMethod('index'),
            DuplicateSpecificationUniqueMiddlewareHandler::class,
            [],
            $attributeReader->forReflection($reflection->getMethod('index')),
            $classModifiers
        );

        self::assertCount(2, $definitions[0]->middlewareServices);
    }

    public function testExtractorDoesNotRetainClassModifierCollections(): void
    {
        $attributeReader = new RouteAttributeReader();
        $registry        = new ModifierCollectionRegistry();
        $builder         = new RouteDefinitionBuilder(
            $attributeReader,
            new MethodSignatureValidator(),
            new RouteDataNormalizer(),
            $registry
        );
        $extractor = new AttributeRouteExtractor(
            new ClassEligibilityValidator(),
            $attributeReader,
            $builder
        );

        $routes = $extractor->extract([
            AggregatingModifierHandler::class,
            DuplicateSpecificationUniqueMiddlewareHandler::class,
        ]);
        unset($routes);

        $collections = (new ReflectionProperty($registry, 'collections'))->getValue($registry);

        self::assertSame([], $collections);
    }
}
