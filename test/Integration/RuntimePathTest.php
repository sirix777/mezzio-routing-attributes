<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Integration;

use Mezzio\Router\Route;
use Mezzio\Router\RouteCollector;
use Mezzio\Router\RouteCollectorInterface;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\AttributeRouteProvider;
use Sirix\Mezzio\Routing\Attributes\AttributeRouteProviderFactory;
use Sirix\Mezzio\Routing\Attributes\Cache\NullRouteRegistrarCache;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteRegistrarCacheInterface;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;
use Sirix\Mezzio\Routing\Attributes\ConfigProvider;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveredClassesResolverInterface;
use Sirix\Mezzio\Routing\Attributes\Discovery\NullDiscoveredClassesResolver;
use Sirix\Mezzio\Routing\Attributes\DuplicateRouteResolver;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;
use Sirix\Mezzio\Routing\Attributes\MiddlewarePipelineFactory;
use Sirix\Mezzio\Routing\Attributes\RouteCollectorDelegator;
use Sirix\Mezzio\Routing\Attributes\ServiceMiddlewareResolver;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\PingHandler;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\AttributeRouteExtractorBuilder;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\InMemoryContainer;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\RecordingRouteCollector;

use function array_map;

final class RuntimePathTest extends TestCase
{
    public function testConfigFactoryExtractorAndCollectorWorkTogether(): void
    {
        $collector = new RecordingRouteCollector();
        $extractor = AttributeRouteExtractorBuilder::create();
        $container = new InMemoryContainer([
            'config'                                  => [
                'routing_attributes' => [
                    'classes' => [
                        PingHandler::class,
                    ],
                ],
            ],
            AttributeRouteExtractorInterface::class   => $extractor,
            PingHandler::class                        => new PingHandler(),
            RouteRegistrarCacheInterface::class       => new NullRouteRegistrarCache(),
            DuplicateRouteResolver::class             => new DuplicateRouteResolver('throw'),
            DiscoveredClassesResolverInterface::class => new NullDiscoveredClassesResolver(),
        ]);
        $container->set(
            MiddlewarePipelineFactory::class,
            new MiddlewarePipelineFactory($container, new ServiceMiddlewareResolver())
        );

        $provider = (new AttributeRouteProviderFactory())($container);
        self::assertInstanceOf(AttributeRouteProvider::class, $provider);

        $provider->registerRoutes($collector);

        self::assertSame(2, $collector->routeCalls);
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

        $container = new InMemoryContainer([]);

        $rootConfig = [
            'routing_attributes' => [
                'classes' => [PingHandler::class],
            ],
        ];
        $container->set('config', $rootConfig);
        $container->set(RoutingAttributesConfig::class, RoutingAttributesConfig::fromRootConfig($rootConfig));
        $container->set(AttributeRouteExtractorInterface::class, AttributeRouteExtractorBuilder::create());
        $container->set(PingHandler::class, new PingHandler());
        $container->set(RouteRegistrarCacheInterface::class, new NullRouteRegistrarCache());
        $container->set(DuplicateRouteResolver::class, new DuplicateRouteResolver('throw'));
        $container->set(DiscoveredClassesResolverInterface::class, new NullDiscoveredClassesResolver());
        $container->set(
            MiddlewarePipelineFactory::class,
            new MiddlewarePipelineFactory($container, new ServiceMiddlewareResolver())
        );
        $container->set(
            AttributeRouteProvider::class,
            (new AttributeRouteProviderFactory())($container)
        );

        $collector = new RecordingRouteCollector();

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
            array_map(
                static fn (Route $route): array => [$route->getPath(), $route->getAllowedMethods(), $route->getName()],
                $collector->routes
            )
        );
    }
}
