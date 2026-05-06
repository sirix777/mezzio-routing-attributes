<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Command;

use Mezzio\Router\Route;
use Mezzio\Router\RouteCollectorInterface;

final readonly class RouteTableProvider
{
    public function __construct(private RouteCollectorInterface $routeCollector, private RouteConfigLoaderInterface $routeConfigLoader) {}

    /**
     * @return list<Route>
     */
    public function getRoutes(): array
    {
        $this->routeConfigLoader->load();

        return $this->routeCollector->getRoutes();
    }
}
