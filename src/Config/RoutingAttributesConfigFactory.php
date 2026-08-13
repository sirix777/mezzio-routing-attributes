<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Config;

use Psr\Container\ContainerInterface;

final class RoutingAttributesConfigFactory
{
    public function __invoke(ContainerInterface $container): RoutingAttributesConfig
    {
        $rootConfig = $container->has('config') ? $container->get('config') : [];

        return RoutingAttributesConfig::fromRootConfig($rootConfig);
    }
}
