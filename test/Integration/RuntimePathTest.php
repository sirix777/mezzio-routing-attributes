<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Integration;

use Mezzio\Router\Route;
use Mezzio\Router\RouteCollector;
use Mezzio\Router\RouteCollectorInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Sirix\Mezzio\Routing\Attributes\AttributeRouteProvider;
use Sirix\Mezzio\Routing\Attributes\AttributeRouteProviderFactory;
use Sirix\Mezzio\Routing\Attributes\ConfigProvider;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractor;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;
use Sirix\Mezzio\Routing\Attributes\RouteCollectorDelegator;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\PingHandler;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\InMemoryContainer;

use function array_key_exists;

final class RuntimePathTest extends TestCase
{
    public function testConfigFactoryExtractorAndCollectorWorkTogether(): void
    {
        $collector = $this->createMock(RouteCollectorInterface::class);
        $extractor = new AttributeRouteExtractor();
        $container = new InMemoryContainer([
            'config' => [
                'routing_attributes' => [
                    'classes' => [
                        PingHandler::class,
                    ],
                ],
            ],
            AttributeRouteExtractorInterface::class => $extractor,
            PingHandler::class => new PingHandler(),
        ]);

        $provider = (new AttributeRouteProviderFactory())($container);
        self::assertInstanceOf(AttributeRouteProvider::class, $provider);

        $collector
            ->expects(self::exactly(2))
            ->method('route')
            ->willReturnCallback(
                static fn (string $path, MiddlewareInterface $middleware, ?array $methods = null, ?string $name = null): Route => new Route(
                    '/runtime',
                    $middleware,
                    ['GET'],
                    'runtime.route'
                )
            )
        ;

        $provider->registerRoutes($collector);
    }

    public function testConfigProviderWiringWorksForRouteCollectorDelegation(): void
    {
        $packageConfig = (new ConfigProvider())();
        self::assertSame(
            AttributeRouteProviderFactory::class,
            $packageConfig['dependencies']['factories'][AttributeRouteProvider::class]
        );
        self::assertSame(
            [RouteCollectorDelegator::class],
            $packageConfig['dependencies']['delegators'][RouteCollector::class]
        );

        $container = new class implements ContainerInterface {
            /** @var array<string, mixed> */
            private array $services = [];

            public function set(string $id, mixed $service): void
            {
                $this->services[$id] = $service;
            }

            public function get(string $id): mixed
            {
                return $this->services[$id];
            }

            public function has(string $id): bool
            {
                return array_key_exists($id, $this->services);
            }
        };

        $container->set('config', [
            'routing_attributes' => [
                'classes' => [PingHandler::class],
            ],
        ]);
        $container->set(AttributeRouteExtractorInterface::class, new AttributeRouteExtractor());
        $container->set(PingHandler::class, new PingHandler());
        $container->set(
            AttributeRouteProvider::class,
            (new AttributeRouteProviderFactory())($container)
        );

        $observedRoutes = [];
        $collector = $this->createMock(RouteCollectorInterface::class);
        $collector
            ->expects(self::exactly(2))
            ->method('route')
            ->willReturnCallback(static function(
                string $path,
                MiddlewareInterface $middleware,
                ?array $methods = null,
                ?string $name = null
            ) use (&$observedRoutes): Route {
                $observedRoutes[] = [$path, $methods, $name];

                return new Route('/runtime', $middleware, ['GET'], 'runtime.route');
            })
        ;

        $result = (new RouteCollectorDelegator())(
            $container,
            RouteCollector::class,
            static fn (): RouteCollectorInterface => $collector
        );

        self::assertSame($collector, $result);
        self::assertSame(
            [
                ['/ping', ['GET'], 'ping'],
                ['/ping', ['POST'], 'ping.create'],
            ],
            $observedRoutes
        );
    }
}
