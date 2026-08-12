<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Command;

use Psr\Container\ContainerInterface;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteRegistrarCacheInterface;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveredClassesResolverInterface;
use Sirix\Mezzio\Routing\Attributes\DuplicateRouteResolver;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;
use Sirix\Mezzio\Routing\Attributes\RouteCacheWarmer;

final class WarmRouteCacheCommandFactory
{
    public function __invoke(ContainerInterface $container): WarmRouteCacheCommand
    {
        $rootConfig = $container->has('config') ? $container->get('config') : [];
        $config     = RoutingAttributesConfig::fromRootConfig($rootConfig);

        if (null === $config->cacheFile) {
            return new WarmRouteCacheCommand(null, null);
        }

        return new WarmRouteCacheCommand(
            new RouteCacheWarmer(
                $config,
                $container->get(AttributeRouteExtractorInterface::class),
                $container->get(DuplicateRouteResolver::class),
                $container->get(DiscoveredClassesResolverInterface::class),
                $container->get(RouteRegistrarCacheInterface::class)
            ),
            $config->cacheFile
        );
    }
}
