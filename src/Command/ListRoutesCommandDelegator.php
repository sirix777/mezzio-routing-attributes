<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Command;

use Psr\Container\ContainerInterface;

use function is_array;

final class ListRoutesCommandDelegator
{
    public function __invoke(ContainerInterface $container, string $name, callable $callback): object
    {
        $config = $container->has('config') ? $container->get('config') : [];
        $routingAttributes = is_array($config) && isset($config['routing_attributes']) && is_array($config['routing_attributes'])
            ? $config['routing_attributes']
            : [];

        if (($routingAttributes['override_mezzio_routes_list_command'] ?? false) !== true) {
            return $callback();
        }

        return $container->get(ListRoutesCommand::class);
    }
}
