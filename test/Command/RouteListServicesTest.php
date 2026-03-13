<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Command;

use Mezzio\Router\Route;
use Mezzio\Router\RouteCollectorInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\AttributeRouteProvider;
use Sirix\Mezzio\Routing\Attributes\Command\RouteListFilter;
use Sirix\Mezzio\Routing\Attributes\Command\RouteListFormatter;
use Sirix\Mezzio\Routing\Attributes\Command\RouteListSorter;
use Sirix\Mezzio\Routing\Attributes\Command\RouteTableProvider;

final class RouteListServicesTest extends TestCase
{
    public function testRouteTableProviderLoadsConfigBeforeReadingRoutes(): void
    {
        $loaderCalled = false;

        $collector = $this->createMock(RouteCollectorInterface::class);
        $collector
            ->expects(self::once())
            ->method('getRoutes')
            ->willReturn([])
        ;

        $provider = new RouteTableProvider($collector, static function() use (&$loaderCalled): void {
            $loaderCalled = true;
        });
        $provider->getRoutes();

        self::assertTrue($loaderCalled);
    }

    public function testRouteListFilterFiltersByMethod(): void
    {
        $filter = new RouteListFilter();
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };
        $routes = [
            new Route('/users', $middleware, ['GET'], 'users.list'),
            new Route('/users', $middleware, ['POST'], 'users.create'),
            new Route('/health', $middleware, ['GET'], 'health'),
        ];

        $filtered = $filter->filter($routes, false, false, false, 'POST');

        self::assertCount(1, $filtered);
        self::assertSame('users.create', $filtered[0]->getName());
    }

    public function testRouteListFilterUsesUnderlyingMiddlewareClassForAttributeRoute(): void
    {
        $filter = new RouteListFilter();
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };
        $route = new Route('/attribute', $middleware, ['GET'], 'attribute.route');
        $route->setOptions([
            AttributeRouteProvider::ROUTE_OPTION_MIDDLEWARE_DISPLAY => 'mw.one -> handler::handle',
        ]);

        $filtered = $filter->filter([$route], false, false, $middleware::class, false);

        self::assertCount(1, $filtered);
        self::assertSame('attribute.route', $filtered[0]->getName());
    }

    public function testRouteListSorterSortsByPath(): void
    {
        $sorter = new RouteListSorter();
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };
        $routes = [
            new Route('/b', $middleware, ['GET'], 'b'),
            new Route('/a', $middleware, ['GET'], 'a'),
        ];

        $sorted = $sorter->sort($routes, 'path');

        self::assertSame('/a', $sorted[0]->getPath());
        self::assertSame('/b', $sorted[1]->getPath());
    }

    public function testRouteListFormatterUsesAttributeMiddlewareDisplayWhenAvailable(): void
    {
        $formatter = new RouteListFormatter();
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };
        $route = new Route('/x', $middleware, ['GET'], 'x');
        $route->setOptions([
            AttributeRouteProvider::ROUTE_OPTION_MIDDLEWARE_DISPLAY => 'mw.one -> handler::handle',
        ]);

        $rows = $formatter->formatRows([$route]);

        self::assertSame('mw.one -> handler::handle', $rows[0]['middleware']);
    }

    public function testRouteListFormatterUsesOriginalMiddlewareClassForNonAttributeRoute(): void
    {
        $formatter = new RouteListFormatter();
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };
        $route = new Route('/classic-demo', $middleware, ['GET'], 'classic.demo');

        $rows = $formatter->formatRows([$route]);

        self::assertSame($middleware::class, $rows[0]['middleware']);
    }
}
