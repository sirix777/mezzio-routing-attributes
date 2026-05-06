<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteRegistrarCacheInterface;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveredClassesResolverInterface;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveryClassMapResolver;
use Sirix\Mezzio\Routing\Attributes\Discovery\NullDiscoveredClassesResolver;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;
use Sirix\Mezzio\Routing\Attributes\Factory\CompiledRouteRegistrarCacheFactory;
use Sirix\Mezzio\Routing\Attributes\Factory\DiscoveryClassMapResolverFactory;
use Sirix\Mezzio\Routing\Attributes\Factory\DuplicateRouteResolverFactory;
use Sirix\Mezzio\Routing\Attributes\Factory\MiddlewarePipelineFactoryFactory;

use function array_merge;
use function array_unique;
use function array_values;

final class AttributeRouteProviderFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): AttributeRouteProvider
    {
        $rootConfig = $container->has('config') ? $container->get('config') : [];
        $config = RoutingAttributesConfig::fromRootConfig($rootConfig);

        $cacheFactory = new CompiledRouteRegistrarCacheFactory();
        $routeRegistrarCache = $cacheFactory($config->cacheFile);

        $classes = array_values(array_unique(array_merge(
            $config->classes,
            $this->discoveryResolver($container, $config, $routeRegistrarCache)->resolve()
        )));

        $duplicateResolverFactory = new DuplicateRouteResolverFactory();
        $middlewareFactoryFactory = new MiddlewarePipelineFactoryFactory();
        $attributeRouteExtractor = $container->get(AttributeRouteExtractorInterface::class);

        return new AttributeRouteProvider(
            $attributeRouteExtractor,
            $classes,
            $duplicateResolverFactory($config->duplicateStrategy),
            $middlewareFactoryFactory($container),
            $routeRegistrarCache
        );
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function discoveryResolver(
        ContainerInterface $container,
        RoutingAttributesConfig $config,
        RouteRegistrarCacheInterface $routeRegistrarCache
    ): DiscoveredClassesResolverInterface {
        if (! $config->discoveryEnabled || $routeRegistrarCache->hasUsableArtifact()) {
            return new NullDiscoveredClassesResolver();
        }

        if ($container->has(DiscoveredClassesResolverInterface::class)) {
            return $container->get(DiscoveredClassesResolverInterface::class);
        }

        if ($container->has(DiscoveryClassMapResolver::class)) {
            return $container->get(DiscoveryClassMapResolver::class);
        }

        $factory = new DiscoveryClassMapResolverFactory();

        return $factory->createFromConfig($config);
    }
}
