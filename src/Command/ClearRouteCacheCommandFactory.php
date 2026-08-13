<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Command;

use Psr\Container\ContainerInterface;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;

final class ClearRouteCacheCommandFactory
{
    public function __invoke(ContainerInterface $container): ClearRouteCacheCommand
    {
        $config = $container->get(RoutingAttributesConfig::class);

        return new ClearRouteCacheCommand($config->cacheFile);
    }
}
