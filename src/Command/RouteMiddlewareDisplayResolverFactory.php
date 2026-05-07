<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Command;

use Psr\Container\ContainerInterface;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;

final class RouteMiddlewareDisplayResolverFactory
{
    public function __invoke(ContainerInterface $container): RouteMiddlewareDisplayResolver
    {
        $rootConfig = $container->has('config') ? $container->get('config') : [];
        $config = RoutingAttributesConfig::fromRootConfig($rootConfig);

        return new RouteMiddlewareDisplayResolver($config->classicRoutesMiddlewareDisplay);
    }
}
