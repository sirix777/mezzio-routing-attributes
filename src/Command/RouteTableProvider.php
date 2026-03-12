<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Command;

use Mezzio\Router\Route;
use Mezzio\Router\RouteCollector;

final readonly class RouteTableProvider
{
    /** @var null|callable():void */
    private mixed $loadConfig;

    /**
     * @param null|callable():void $loadConfig
     */
    public function __construct(private RouteCollector $routeCollector, mixed $loadConfig = null)
    {
        $this->loadConfig = $loadConfig;
    }

    /**
     * @return list<Route>
     */
    public function getRoutes(): array
    {
        if (null !== $this->loadConfig) {
            ($this->loadConfig)();
        }

        return $this->routeCollector->getRoutes();
    }
}
