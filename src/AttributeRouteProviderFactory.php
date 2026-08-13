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

final class AttributeRouteProviderFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): AttributeRouteProvider
    {
        $config = $container->get(RoutingAttributesConfig::class);

        return new AttributeRouteProvider(
            $container->get(AttributeRouteExtractorInterface::class),
            $config->classes,
            $container->get(DuplicateRouteResolver::class),
            $container->get(MiddlewarePipelineFactory::class),
            $container->get(RouteRegistrarCacheInterface::class),
            $container->get(DiscoveredClassesResolverInterface::class)
        );
    }
}
