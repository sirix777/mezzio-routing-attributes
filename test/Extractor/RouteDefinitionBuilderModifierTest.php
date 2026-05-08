<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sirix\Mezzio\Routing\Attributes\Extractor\MethodSignatureValidator;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteAttributeReader;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteDataNormalizer;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteDefinitionBuilder;
use Sirix\Mezzio\Routing\Attributes\RouteDefinition;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\ModifierHandler;

final class RouteDefinitionBuilderModifierTest extends TestCase
{
    public function testCollectsMiddlewareAndDefaultsFromModifierAttributes(): void
    {
        $attributeReader = new RouteAttributeReader();
        $builder = new RouteDefinitionBuilder(
            $attributeReader,
            new MethodSignatureValidator(),
            new RouteDataNormalizer()
        );

        /** @var ReflectionClass<object> $reflection */
        $reflection = new ReflectionClass(ModifierHandler::class);
        $definitions = $builder->buildForClass($reflection, ModifierHandler::class);

        self::assertCount(1, $definitions);
        $route = $definitions[0];

        self::assertInstanceOf(RouteDefinition::class, $route);
        self::assertSame('/modifier', $route->path);
        self::assertSame(['modifier.middleware'], $route->middlewareServices);
        self::assertSame(['modifier_key' => 'modifier_value'], $route->defaults);
    }
}
