<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Command;

use Psr\Container\ContainerInterface;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;

final class ListRoutesCommandDelegator
{
    public function __invoke(ContainerInterface $container, string $name, callable $callback): object
    {
        $config = $container->get(RoutingAttributesConfig::class);
        if (! $config->overrideMezzioRoutesListCommand) {
            return $callback();
        }

        return $container->get(ListRoutesCommand::class);
    }
}
