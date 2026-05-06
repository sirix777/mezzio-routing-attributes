<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes;

use Mezzio\Router\RouteCollectorInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;

final class RouteCollectorDelegator
{
    /**
     * @param callable():mixed $callback
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, string $name, callable $callback): RouteCollectorInterface
    {
        $collector = $callback();

        if (! $collector instanceof RouteCollectorInterface) {
            throw InvalidConfigurationException::invalidRouteCollectorCallbackReturn($collector);
        }

        $container->get(AttributeRouteProvider::class)->registerRoutes($collector);

        return $collector;
    }
}
