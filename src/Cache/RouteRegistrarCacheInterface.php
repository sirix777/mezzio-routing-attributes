<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Cache;

use Mezzio\Router\RouteCollectorInterface;
use Sirix\Mezzio\Routing\Attributes\MiddlewarePipelineFactory;
use Sirix\Mezzio\Routing\Attributes\RouteDefinition;

/**
 * @internal
 */
interface RouteRegistrarCacheInterface
{
    public function registerRoutes(RouteCollectorInterface $collector, MiddlewarePipelineFactory $pipelineFactory): bool;

    /**
     * @param list<RouteDefinition> $routes
     */
    public function save(array $routes): bool;
}
