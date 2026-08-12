<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Command;

use Closure;
use Mezzio\Router\Route;
use Mezzio\Router\RouteCollectorInterface;

final readonly class RouteTableProvider
{
    /** @param null|Closure(): void $loadRouteConfig */
    public function __construct(private RouteCollectorInterface $routeCollector, private ?Closure $loadRouteConfig = null) {}

    /**
     * @return list<Route>
     */
    public function getRoutes(): array
    {
        $this->loadRouteConfig?->__invoke();

        return $this->routeCollector->getRoutes();
    }
}
