<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Command;

use Psr\Container\ContainerInterface;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;

final class RouteMiddlewareDisplayResolverFactory
{
    public function __invoke(ContainerInterface $container): RouteMiddlewareDisplayResolver
    {
        $config = $container->get(RoutingAttributesConfig::class);

        return new RouteMiddlewareDisplayResolver($config->classicRoutesMiddlewareDisplay);
    }
}
