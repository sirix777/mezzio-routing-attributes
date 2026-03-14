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
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Command\RouteListFilter;
use Sirix\Mezzio\Routing\Attributes\Command\RouteListFormatter;
use Sirix\Mezzio\Routing\Attributes\Command\RouteListSorter;
use Sirix\Mezzio\Routing\Attributes\Command\RouteMiddlewareDisplayResolver;
use Sirix\Mezzio\Routing\Attributes\Command\RouteTableProvider;
use Sirix\Mezzio\Routing\Attributes\MiddlewarePipelineFactory;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\InMemoryContainer;

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

    public function testRouteListFilterUsesAttributeMiddlewareDisplayForAttributeRoute(): void
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
            'sirix_routing_attributes.middleware_display' => 'App\Middleware\PackageVersionHeaderMiddleware -> handler::handle',
        ]);

        $filtered = $filter->filter([$route], false, false, 'PackageVersionHeaderMiddleware', false);

        self::assertCount(1, $filtered);
        self::assertSame('attribute.route', $filtered[0]->getName());
    }

    public function testRouteListFilterUsesResolvedServiceNameForClassicLazyLoadedRouteWhenConfigured(): void
    {
        $filter = new RouteListFilter(
            new RouteMiddlewareDisplayResolver(
                RouteMiddlewareDisplayResolver::CLASSIC_ROUTES_MIDDLEWARE_DISPLAY_RESOLVED
            )
        );
        $route = new Route(
            '/classic-demo',
            new class implements MiddlewareInterface {
                public string $middlewareName = 'App\Handler\ClassicRouteHandler';

                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                {
                    return $handler->handle($request);
                }
            },
            ['GET'],
            'classic.demo'
        );

        $filtered = $filter->filter([$route], false, false, 'App\Handler\ClassicRouteHandler', false);

        self::assertCount(1, $filtered);
        self::assertSame('classic.demo', $filtered[0]->getName());
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
            'sirix_routing_attributes.middleware_display' => 'mw.one -> handler::handle',
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

    public function testRouteListFormatterUsesResolvedServiceNameForClassicLazyLoadedRouteWhenConfigured(): void
    {
        $formatter = new RouteListFormatter(
            new RouteMiddlewareDisplayResolver(
                RouteMiddlewareDisplayResolver::CLASSIC_ROUTES_MIDDLEWARE_DISPLAY_RESOLVED
            )
        );
        $route = new Route(
            '/classic-demo',
            new class implements MiddlewareInterface {
                public string $middlewareName = 'App\Handler\ClassicRouteHandler';

                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                {
                    return $handler->handle($request);
                }
            },
            ['GET'],
            'classic.demo'
        );

        $rows = $formatter->formatRows([$route]);

        self::assertSame('App\Handler\ClassicRouteHandler', $rows[0]['middleware']);
    }

    public function testRouteListFormatterResolvesPrivateClassicLazyMiddlewareName(): void
    {
        $formatter = new RouteListFormatter(
            new RouteMiddlewareDisplayResolver(
                RouteMiddlewareDisplayResolver::CLASSIC_ROUTES_MIDDLEWARE_DISPLAY_RESOLVED
            )
        );
        $middlewareName = 'App\Handler\PrivateClassicHandler';
        $route = new Route(
            '/classic-private',
            new class($middlewareName) implements MiddlewareInterface {
                public function __construct(private readonly string $middlewareName) {}

                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                {
                    if ('__unexpected__' === $this->middlewareName) {
                        throw new RuntimeException('Unexpected middleware marker.');
                    }

                    return $handler->handle($request);
                }
            },
            ['GET'],
            'classic.private'
        );

        $rows = $formatter->formatRows([$route]);

        self::assertSame('App\Handler\PrivateClassicHandler', $rows[0]['middleware']);
    }

    public function testRouteListFormatterUsesMiddlewareDisplayOptionForLazyAttributeRoute(): void
    {
        $container = new InMemoryContainer([]);
        $factory = new MiddlewarePipelineFactory($container);
        $middleware = $factory->createFromCompiled('handler.service', 'process', ['mw.first']);
        $route = new Route('/lazy', $middleware, ['GET'], 'lazy.route');
        $route->setOptions([
            RouteMiddlewareDisplayResolver::ROUTE_OPTION_MIDDLEWARE_DISPLAY => 'mw.first -> handler.service::process',
        ]);

        $rows = (new RouteListFormatter())->formatRows([$route]);

        self::assertSame('mw.first -> handler.service::process', $rows[0]['middleware']);
    }

    public function testRouteListFilterUsesMiddlewareDisplayOptionForLazyAttributeRoute(): void
    {
        $middleware = (new MiddlewarePipelineFactory(new InMemoryContainer([])))
            ->createFromCompiled('handler.single', 'process', [])
        ;
        $route = new Route('/lazy-single', $middleware, ['GET'], 'lazy.single');
        $route->setOptions([
            RouteMiddlewareDisplayResolver::ROUTE_OPTION_MIDDLEWARE_DISPLAY => 'handler.single::process',
        ]);

        $filtered = (new RouteListFilter())->filter([$route], false, false, 'handler.single', false);

        self::assertCount(1, $filtered);
        self::assertSame('lazy.single', $filtered[0]->getName());
    }
}
