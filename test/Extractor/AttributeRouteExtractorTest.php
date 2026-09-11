<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidMiddlewareClassException;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidRouteDefinitionException;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\AggregatingModifierHandler;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\AmbiguousSpecificationSignatureHandler;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\CallableActionController;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\CallableActionInvalidHandlerParameter;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\CallableActionInvalidIntersectionParameter;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\CallableActionInvalidReturnType;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\CallableActionInvalidSignature;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\CallableActionInvalidUnionReturnType;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\CallableActionInvalidVariadicHandlerParameter;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\CallableActionPrivateMethod;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\CallableActionUnionHandlerParameter;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\CallableActionVariadicHandlerParameter;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\CallableActionWithTrailingOptionalParameter;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\ConflictingSpecificationUniqueMiddlewareHandler;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\ConflictingStringAndSpecificationUniqueMiddlewareHandler;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\ConflictingStringUniqueMiddlewareHandler;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\CountingAttributeModifier;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\DuplicateOrdinaryModifierHandler;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\DuplicateSpecificationUniqueMiddlewareHandler;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\LegacyDefaultsAggregatingModifierHandler;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\MethodRouteWithClassModifierHandler;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\MultiMethodRouteWithClassModifierHandler;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\MultipleAggregatingModifierHandler;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\NotMiddleware;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\PingHandler;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\PingRequestHandler;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\StackedHandler;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\StackFirstMiddleware;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\StackSecondMiddleware;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\AttributeRouteExtractorBuilder;

final class AttributeRouteExtractorTest extends TestCase
{
    public function testExtractsClassLevelRouteAttributes(): void
    {
        $extractor = AttributeRouteExtractorBuilder::create();
        $routes    = $extractor->extract([PingHandler::class]);

        self::assertCount(2, $routes);

        self::assertSame('/ping', $routes[0]->path);
        self::assertSame(['GET'], $routes[0]->methods);
        self::assertSame('ping', $routes[0]->name);
        self::assertSame(PingHandler::class, $routes[0]->handlerService);
        self::assertSame('process', $routes[0]->handlerMethod);
        self::assertSame([], $routes[0]->middlewareServices);

        self::assertSame('/ping', $routes[1]->path);
        self::assertSame(['POST'], $routes[1]->methods);
        self::assertSame('ping.create', $routes[1]->name);
        self::assertSame(PingHandler::class, $routes[1]->handlerService);
        self::assertSame('process', $routes[1]->handlerMethod);
        self::assertSame([], $routes[1]->middlewareServices);
    }

    public function testThrowsForNonExistentClass(): void
    {
        $extractor = AttributeRouteExtractorBuilder::create();

        $this->expectException(InvalidMiddlewareClassException::class);

        $extractor->extract(['Foo\Bar\MissingClass']);
    }

    public function testThrowsForEmptyConfiguredClassEntry(): void
    {
        $extractor = AttributeRouteExtractorBuilder::create();

        $this->expectException(InvalidMiddlewareClassException::class);

        $extractor->extract(['']);
    }

    public function testThrowsForClassThatIsNotMiddleware(): void
    {
        $extractor = AttributeRouteExtractorBuilder::create();

        $this->expectException(InvalidMiddlewareClassException::class);

        $extractor->extract([NotMiddleware::class]);
    }

    public function testExtractsRequestHandlerRouteAttributes(): void
    {
        $extractor = AttributeRouteExtractorBuilder::create();
        $routes    = $extractor->extract([PingRequestHandler::class]);

        self::assertCount(1, $routes);
        self::assertSame('/ping-handler', $routes[0]->path);
        self::assertSame(['GET'], $routes[0]->methods);
        self::assertSame('ping.handler', $routes[0]->name);
        self::assertSame(PingRequestHandler::class, $routes[0]->handlerService);
        self::assertSame('handle', $routes[0]->handlerMethod);
        self::assertSame([], $routes[0]->middlewareServices);
    }

    public function testExtractsConfiguredMiddlewareStackFromAttribute(): void
    {
        $extractor = AttributeRouteExtractorBuilder::create();
        $routes    = $extractor->extract([StackedHandler::class]);

        self::assertCount(1, $routes);
        self::assertSame('/stacked', $routes[0]->path);
        self::assertSame(
            [
                StackFirstMiddleware::class,
                StackSecondMiddleware::class,
            ],
            $routes[0]->middlewareServices
        );
    }

    public function testAppliesClassAndMethodModifierAttributesToMethodRoutes(): void
    {
        $extractor = AttributeRouteExtractorBuilder::create();
        $routes    = $extractor->extract([MethodRouteWithClassModifierHandler::class]);

        self::assertCount(1, $routes);
        self::assertSame('/method-modifier', $routes[0]->path);
        self::assertSame(
            [
                'route.middleware',
                'class.modifier',
                'method.modifier',
            ],
            $routes[0]->middlewareServices
        );
        self::assertCount(3, $routes[0]->defaults);
        self::assertSame('method', $routes[0]->defaults['scope']);
        self::assertTrue($routes[0]->defaults['classOnly']);
        self::assertSame(1, $routes[0]->defaults['methodOnly']);
    }

    public function testCollectsClassModifierAttributesOnceForMethodRoutes(): void
    {
        CountingAttributeModifier::$instances = 0;

        $extractor = AttributeRouteExtractorBuilder::create();
        $routes    = $extractor->extract([MultiMethodRouteWithClassModifierHandler::class]);

        self::assertCount(2, $routes);
        self::assertSame(1, CountingAttributeModifier::$instances);
        self::assertSame(['counting.modifier'], $routes[0]->middlewareServices);
        self::assertSame(['counting.modifier'], $routes[1]->middlewareServices);
        self::assertSame([
            'counting' => true,
        ], $routes[0]->defaults);
        self::assertSame([
            'counting' => true,
        ], $routes[1]->defaults);
    }

    public function testAggregatesClassAndMethodModifierDefaultsAndDeduplicatesKeyedMiddleware(): void
    {
        $routes = AttributeRouteExtractorBuilder::create()->extract([AggregatingModifierHandler::class]);

        self::assertCount(1, $routes);
        self::assertSame([
            'class.middleware',
            'mapper.middleware',
            'method.middleware',
        ], $routes[0]->middlewareServices);
        self::assertSame([
            'mappings' => ['query', 'body'],
        ], $routes[0]->defaults);
    }

    public function testDoesNotDeduplicateMiddlewareFromOrdinaryModifiers(): void
    {
        $routes = AttributeRouteExtractorBuilder::create()->extract([DuplicateOrdinaryModifierHandler::class]);

        self::assertCount(1, $routes);
        self::assertSame([
            'ordinary.duplicate',
            'ordinary.duplicate',
        ], $routes[0]->middlewareServices);
    }

    public function testPreservesClassAndMethodOrderForMultipleAggregatingModifiers(): void
    {
        $routes = AttributeRouteExtractorBuilder::create()->extract([MultipleAggregatingModifierHandler::class]);

        self::assertCount(1, $routes);
        self::assertSame([
            'mapper.middleware',
            'validator.middleware',
        ], $routes[0]->middlewareServices);
        self::assertSame([
            'mappings' => ['query', 'body', 'headers'],
        ], $routes[0]->defaults);
    }

    public function testAggregatingModifierIgnoresLegacyDefaultsBeforeMerging(): void
    {
        $routes = AttributeRouteExtractorBuilder::create()->extract([LegacyDefaultsAggregatingModifierHandler::class]);

        self::assertSame([
            'mappings' => ['query', 'body'],
        ], $routes[0]->defaults);
    }

    public function testRejectsConflictingStringUniqueMiddleware(): void
    {
        $this->expectException(InvalidRouteDefinitionException::class);
        $this->expectExceptionMessage('conflicting middleware services');

        AttributeRouteExtractorBuilder::create()->extract([ConflictingStringUniqueMiddlewareHandler::class]);
    }

    public function testRejectsStringAndSpecificationForTheSameUniqueMiddlewareKey(): void
    {
        $this->expectException(InvalidRouteDefinitionException::class);
        $this->expectExceptionMessage('conflicting middleware services');

        AttributeRouteExtractorBuilder::create()->extract([ConflictingStringAndSpecificationUniqueMiddlewareHandler::class]);
    }

    public function testDeduplicatesEquivalentUniqueMiddlewareSpecifications(): void
    {
        $routes = AttributeRouteExtractorBuilder::create()->extract([DuplicateSpecificationUniqueMiddlewareHandler::class]);

        self::assertCount(1, $routes[0]->middlewareServices);
        self::assertInstanceOf(MiddlewareSpecification::class, $routes[0]->middlewareServices[0]);
        self::assertSame('mapper.factory', $routes[0]->middlewareServices[0]->factory);
        self::assertSame([
            'mode' => 'strict',
        ], $routes[0]->middlewareServices[0]->arguments);
    }

    public function testRejectsSpecificationsWithAnAmbiguousMatchingSignature(): void
    {
        $this->expectException(InvalidRouteDefinitionException::class);
        $this->expectExceptionMessage('conflicting middleware services');

        AttributeRouteExtractorBuilder::create()->extract([AmbiguousSpecificationSignatureHandler::class]);
    }

    public function testRejectsConflictingSpecificationUniqueMiddleware(): void
    {
        $this->expectException(InvalidRouteDefinitionException::class);
        $this->expectExceptionMessage('conflicting middleware services');

        AttributeRouteExtractorBuilder::create()->extract([ConflictingSpecificationUniqueMiddlewareHandler::class]);
    }

    public function testAllowsCallableActionClassInCallableMode(): void
    {
        $extractor = AttributeRouteExtractorBuilder::create(true);
        $routes    = $extractor->extract([CallableActionController::class]);

        self::assertCount(1, $routes);
        self::assertSame('/callable-action', $routes[0]->path);
        self::assertSame('callable.action', $routes[0]->name);
        self::assertSame(CallableActionController::class, $routes[0]->handlerService);
        self::assertSame('index', $routes[0]->handlerMethod);
    }

    public function testThrowsForCallableActionClassInPsr15Mode(): void
    {
        $extractor = AttributeRouteExtractorBuilder::create(false);

        $this->expectException(InvalidMiddlewareClassException::class);

        $extractor->extract([CallableActionController::class]);
    }

    public function testThrowsForCallableActionWithNonPublicMethodRoute(): void
    {
        $extractor = AttributeRouteExtractorBuilder::create(true);

        $this->expectException(InvalidRouteDefinitionException::class);
        $this->expectExceptionMessage('must be public');

        $extractor->extract([CallableActionPrivateMethod::class]);
    }

    public function testThrowsForCallableActionWithInvalidMethodSignature(): void
    {
        $extractor = AttributeRouteExtractorBuilder::create(true);

        $this->expectException(InvalidRouteDefinitionException::class);
        $this->expectExceptionMessage('incompatible first parameter');

        $extractor->extract([CallableActionInvalidSignature::class]);
    }

    public function testThrowsForCallableActionWithInvalidDeclaredReturnType(): void
    {
        $extractor = AttributeRouteExtractorBuilder::create(true);

        $this->expectException(InvalidRouteDefinitionException::class);
        $this->expectExceptionMessage('must declare');

        $extractor->extract([CallableActionInvalidReturnType::class]);
    }

    public function testThrowsForCallableActionWithInvalidUnionReturnType(): void
    {
        $extractor = AttributeRouteExtractorBuilder::create(true);

        $this->expectException(InvalidRouteDefinitionException::class);
        $this->expectExceptionMessage('must declare');

        $extractor->extract([CallableActionInvalidUnionReturnType::class]);
    }

    public function testThrowsForCallableActionWithInvalidIntersectionParameter(): void
    {
        $extractor = AttributeRouteExtractorBuilder::create(true);

        $this->expectException(InvalidRouteDefinitionException::class);
        $this->expectExceptionMessage('incompatible first parameter');

        $extractor->extract([CallableActionInvalidIntersectionParameter::class]);
    }

    public function testAllowsCallableActionWithTrailingOptionalParameter(): void
    {
        $routes = AttributeRouteExtractorBuilder::create(true)->extract([CallableActionWithTrailingOptionalParameter::class]);

        self::assertCount(1, $routes);
        self::assertSame(CallableActionWithTrailingOptionalParameter::class, $routes[0]->handlerService);
    }

    #[DataProvider('validCallableHandlerParameterClasses')]
    public function testAllowsCallableActionWithCompatibleHandlerParameter(string $className): void
    {
        $routes = AttributeRouteExtractorBuilder::create(true)->extract([$className]);

        self::assertCount(1, $routes);
        self::assertSame($className, $routes[0]->handlerService);
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function validCallableHandlerParameterClasses(): iterable
    {
        yield 'union handler parameter' => [CallableActionUnionHandlerParameter::class];

        yield 'variadic handler parameter' => [CallableActionVariadicHandlerParameter::class];
    }

    #[DataProvider('invalidCallableHandlerParameterClasses')]
    public function testThrowsForCallableActionWithIncompatibleHandlerParameter(string $className): void
    {
        $extractor = AttributeRouteExtractorBuilder::create(true);

        $this->expectException(InvalidRouteDefinitionException::class);
        $this->expectExceptionMessage('incompatible handler parameter');

        $extractor->extract([$className]);
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function invalidCallableHandlerParameterClasses(): iterable
    {
        yield 'optional scalar handler parameter' => [CallableActionInvalidHandlerParameter::class];

        yield 'variadic request-only parameter' => [CallableActionInvalidVariadicHandlerParameter::class];
    }
}
