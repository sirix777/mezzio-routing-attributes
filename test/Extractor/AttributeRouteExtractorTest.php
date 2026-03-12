<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidMiddlewareClassException;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidRouteDefinitionException;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractor;
use Sirix\Mezzio\Routing\Attributes\Extractor\ClassEligibilityValidator;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\CallableActionController;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\CallableActionInvalidIntersectionParameter;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\CallableActionInvalidReturnType;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\CallableActionInvalidSignature;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\CallableActionInvalidUnionReturnType;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\CallableActionPrivateMethod;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\NotMiddleware;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\PingHandler;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\PingRequestHandler;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\StackedHandler;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\StackFirstMiddleware;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\StackSecondMiddleware;

class AttributeRouteExtractorTest extends TestCase
{
    public function testExtractsClassLevelRouteAttributes(): void
    {
        $extractor = new AttributeRouteExtractor();
        $routes = $extractor->extract([PingHandler::class]);

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
        $extractor = new AttributeRouteExtractor();

        $this->expectException(InvalidMiddlewareClassException::class);

        $extractor->extract(['Foo\Bar\MissingClass']);
    }

    public function testThrowsForEmptyConfiguredClassEntry(): void
    {
        $extractor = new AttributeRouteExtractor();

        $this->expectException(InvalidMiddlewareClassException::class);

        $extractor->extract(['']);
    }

    public function testThrowsForClassThatIsNotMiddleware(): void
    {
        $extractor = new AttributeRouteExtractor();

        $this->expectException(InvalidMiddlewareClassException::class);

        $extractor->extract([NotMiddleware::class]);
    }

    public function testExtractsRequestHandlerRouteAttributes(): void
    {
        $extractor = new AttributeRouteExtractor();
        $routes = $extractor->extract([PingRequestHandler::class]);

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
        $extractor = new AttributeRouteExtractor();
        $routes = $extractor->extract([StackedHandler::class]);

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

    public function testAllowsCallableActionClassInCallableMode(): void
    {
        $extractor = new AttributeRouteExtractor(
            new ClassEligibilityValidator(true)
        );
        $routes = $extractor->extract([CallableActionController::class]);

        self::assertCount(1, $routes);
        self::assertSame('/callable-action', $routes[0]->path);
        self::assertSame('callable.action', $routes[0]->name);
        self::assertSame(CallableActionController::class, $routes[0]->handlerService);
        self::assertSame('index', $routes[0]->handlerMethod);
    }

    public function testThrowsForCallableActionClassInPsr15Mode(): void
    {
        $extractor = new AttributeRouteExtractor(
            new ClassEligibilityValidator(false)
        );

        $this->expectException(InvalidMiddlewareClassException::class);

        $extractor->extract([CallableActionController::class]);
    }

    public function testThrowsForCallableActionWithNonPublicMethodRoute(): void
    {
        $extractor = new AttributeRouteExtractor(
            new ClassEligibilityValidator(true)
        );

        $this->expectException(InvalidRouteDefinitionException::class);
        $this->expectExceptionMessage('must be public');

        $extractor->extract([CallableActionPrivateMethod::class]);
    }

    public function testThrowsForCallableActionWithInvalidMethodSignature(): void
    {
        $extractor = new AttributeRouteExtractor(
            new ClassEligibilityValidator(true)
        );

        $this->expectException(InvalidRouteDefinitionException::class);
        $this->expectExceptionMessage('incompatible first parameter');

        $extractor->extract([CallableActionInvalidSignature::class]);
    }

    public function testThrowsForCallableActionWithInvalidDeclaredReturnType(): void
    {
        $extractor = new AttributeRouteExtractor(
            new ClassEligibilityValidator(true)
        );

        $this->expectException(InvalidRouteDefinitionException::class);
        $this->expectExceptionMessage('must declare');

        $extractor->extract([CallableActionInvalidReturnType::class]);
    }

    public function testThrowsForCallableActionWithInvalidUnionReturnType(): void
    {
        $extractor = new AttributeRouteExtractor(
            new ClassEligibilityValidator(true)
        );

        $this->expectException(InvalidRouteDefinitionException::class);
        $this->expectExceptionMessage('must declare');

        $extractor->extract([CallableActionInvalidUnionReturnType::class]);
    }

    public function testThrowsForCallableActionWithInvalidIntersectionParameter(): void
    {
        $extractor = new AttributeRouteExtractor(
            new ClassEligibilityValidator(true)
        );

        $this->expectException(InvalidRouteDefinitionException::class);
        $this->expectExceptionMessage('incompatible first parameter');

        $extractor->extract([CallableActionInvalidIntersectionParameter::class]);
    }
}
