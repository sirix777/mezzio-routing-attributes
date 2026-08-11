<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Factory;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sirix\Mezzio\Routing\Attributes\Cache\NullRouteRegistrarCache;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheGenerator;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheLoader;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheStorage;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteRegistrarCacheInterface;
use Sirix\Mezzio\Routing\Attributes\CompiledRouteRegistrarCache;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;
use Throwable;

use function interface_exists;
use function is_object;

final class CompiledRouteRegistrarCacheFactory
{
    private const PSR_LOGGER_INTERFACE = 'Psr\Log\LoggerInterface';

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
        ?object $logger = null
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

    private function resolveOptionalLogger(ContainerInterface $container): ?object
    {
        if (! interface_exists(self::PSR_LOGGER_INTERFACE) || ! $container->has(self::PSR_LOGGER_INTERFACE)) {
            return null;
        }

        try {
            $logger = $container->get(self::PSR_LOGGER_INTERFACE);
        } catch (Throwable) {
            return null;
        }

        return is_object($logger) ? $logger : null;
    }
}
