<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Cache;

use Mezzio\Router\RouteCollectorInterface;
use Sirix\Mezzio\Routing\Attributes\MiddlewarePipelineFactory;

final readonly class NullRouteRegistrarCache implements RouteRegistrarCacheInterface
{
    public function registerRoutes(RouteCollectorInterface $collector, MiddlewarePipelineFactory $pipelineFactory): bool
    {
        return false;
    }

    public function hasUsableArtifact(): bool
    {
        return false;
    }

    public function save(array $routes): void {}
}
