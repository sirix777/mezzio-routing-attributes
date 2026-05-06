<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Factory;

use Sirix\Mezzio\Routing\Attributes\Cache\NullRouteRegistrarCache;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheGenerator;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheLoader;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheStorage;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteRegistrarCacheInterface;
use Sirix\Mezzio\Routing\Attributes\CompiledRouteRegistrarCache;

final class CompiledRouteRegistrarCacheFactory
{
    public function __invoke(?string $cacheFile): RouteRegistrarCacheInterface
    {
        if (null === $cacheFile) {
            return new NullRouteRegistrarCache();
        }

        return new CompiledRouteRegistrarCache(
            $cacheFile,
            new RouteCacheGenerator(),
            new RouteCacheStorage(),
            new RouteCacheLoader()
        );
    }
}
