<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Factory;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Sirix\Mezzio\Routing\Attributes\Cache\NullRouteRegistrarCache;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheGenerator;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheLoader;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheStorage;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteRegistrarCacheInterface;
use Sirix\Mezzio\Routing\Attributes\CompiledRouteRegistrarCache;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;
use Throwable;

final class CompiledRouteRegistrarCacheFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): RouteRegistrarCacheInterface
    {
        $rootConfig = $container->has('config') ? $container->get('config') : [];
        $config     = RoutingAttributesConfig::fromRootConfig($rootConfig);

        return $this->createFromCacheFile(
            $config->cacheFile,
            $config->cacheFingerprint(),
            $this->resolveOptionalLogger($container)
        );
    }

    public function createFromCacheFile(
        ?string $cacheFile,
        string $configFingerprint = '',
        ?LoggerInterface $logger = null
    ): RouteRegistrarCacheInterface {
        if (null === $cacheFile) {
            return new NullRouteRegistrarCache();
        }

        return new CompiledRouteRegistrarCache(
            $cacheFile,
            new RouteCacheGenerator(),
            new RouteCacheStorage($logger),
            new RouteCacheLoader(),
            $configFingerprint
        );
    }

    private function resolveOptionalLogger(ContainerInterface $container): ?LoggerInterface
    {
        if (! $container->has(LoggerInterface::class)) {
            return null;
        }

        try {
            $logger = $container->get(LoggerInterface::class);
        } catch (Throwable) {
            return null;
        }

        return $logger instanceof LoggerInterface ? $logger : null;
    }
}
