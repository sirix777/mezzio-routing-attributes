<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Command;

use Psr\Container\ContainerInterface;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;

final class ClearRouteCacheCommandFactory
{
    public function __invoke(ContainerInterface $container): ClearRouteCacheCommand
    {
        $rootConfig = $container->has('config') ? $container->get('config') : [];
        $config = RoutingAttributesConfig::fromRootConfig($rootConfig);

        return new ClearRouteCacheCommand($config->cacheFile);
    }
}
