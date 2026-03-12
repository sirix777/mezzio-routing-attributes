<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Command;

use Mezzio\Router\RouteCollector;
use Mezzio\Tooling\Routes\ConfigLoaderInterface;
use Psr\Container\ContainerInterface;

use function interface_exists;

final class ListRoutesCommandFactory
{
    public function __invoke(ContainerInterface $container): ListRoutesCommand
    {
        /** @var RouteCollector $routeCollector */
        $routeCollector = $container->get(RouteCollector::class);

        $loadConfig = null;
        if (
            interface_exists(ConfigLoaderInterface::class)
            && $container->has(ConfigLoaderInterface::class)
        ) {
            $configLoader = $container->get(ConfigLoaderInterface::class);
            $loadConfig = function() use ($configLoader): void {
                $configLoader->load();
            };
        }

        return new ListRoutesCommand(new RouteTableProvider($routeCollector, $loadConfig));
    }
}
