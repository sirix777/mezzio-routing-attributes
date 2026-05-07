<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteRegistrarCacheInterface;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveredClassesResolverInterface;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;

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

        $routeRegistrarCache = $container->get(RouteRegistrarCacheInterface::class);

        $classes = array_values(array_unique(array_merge(
            $config->classes,
            $this->resolveDiscoveredClasses($container, $routeRegistrarCache)
        )));

        return new AttributeRouteProvider(
            $container->get(AttributeRouteExtractorInterface::class),
            $classes,
            $container->get(DuplicateRouteResolver::class),
            $container->get(MiddlewarePipelineFactory::class),
            $routeRegistrarCache
        );
    }

    /**
     * @return list<non-empty-string>
     */
    private function resolveDiscoveredClasses(ContainerInterface $container, RouteRegistrarCacheInterface $routeRegistrarCache): array
    {
        if ($routeRegistrarCache->hasUsableArtifact()) {
            return [];
        }

        return $container->get(DiscoveredClassesResolverInterface::class)->resolve();
    }
}
