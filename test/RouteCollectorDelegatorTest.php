<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes;

use Mezzio\Router\Route;
use Mezzio\Router\RouteCollectorInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\AttributeRouteProvider;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;
use Sirix\Mezzio\Routing\Attributes\RouteCollectorDelegator;
use Sirix\Mezzio\Routing\Attributes\RouteDefinition;
use stdClass;

final class RouteCollectorDelegatorTest extends TestCase
{
    public function testRegistersRoutesViaAttributeProvider(): void
    {
        $middlewareContainer = $this->createMock(ContainerInterface::class);
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $collector = $this->createMock(RouteCollectorInterface::class);
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };

        $extractor
            ->expects(self::once())
            ->method('extract')
            ->with([TestMiddleware::class])
            ->willReturn([
                new RouteDefinition('/delegated', ['GET'], TestMiddleware::class, 'process', [], 'delegated.route'),
            ])
        ;

        $middlewareContainer
            ->expects(self::once())
            ->method('get')
            ->with(TestMiddleware::class)
            ->willReturn($middleware)
        ;

        $collector
            ->expects(self::once())
            ->method('route')
            ->with('/delegated', $middleware, ['GET'], 'delegated.route')
            ->willReturn(new Route('/delegated', $middleware, ['GET'], 'delegated.route'))
        ;

        $provider = new AttributeRouteProvider($middlewareContainer, $extractor, [TestMiddleware::class]);
        $container = $this->createMock(ContainerInterface::class);
        $container
            ->expects(self::once())
            ->method('get')
            ->with(AttributeRouteProvider::class)
            ->willReturn($provider)
        ;

        $result = (new RouteCollectorDelegator())(
            $container,
            RouteCollectorInterface::class,
            static fn () => $collector
        );

        self::assertSame($collector, $result);
    }

    public function testThrowsWhenCallbackDoesNotReturnRouteCollector(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        $this->expectException(InvalidConfigurationException::class);

        (new RouteCollectorDelegator())(
            $container,
            RouteCollectorInterface::class,
            static fn (): object => new stdClass()
        );
    }
}
