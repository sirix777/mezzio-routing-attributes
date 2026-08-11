<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes;

use Mezzio\Router\RouteCollectorInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheGenerator;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheLoader;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheStorage;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteRegistrarCacheInterface;
use Throwable;

use function is_file;

final readonly class CompiledRouteRegistrarCache implements RouteRegistrarCacheInterface
{
    public function __construct(
        private string $cacheFile,
        private RouteCacheGenerator $cacheGenerator,
        private RouteCacheStorage $cacheStorage,
        private RouteCacheLoader $cacheLoader,
        private string $configFingerprint = ''
    ) {}

    public function registerRoutes(RouteCollectorInterface $collector, MiddlewarePipelineFactory $pipelineFactory): bool
    {
        if (! is_file($this->cacheFile)) {
            return false;
        }

        try {
            $artifact = $this->cacheLoader->load($this->cacheFile, $this->configFingerprint);
        } catch (Throwable) {
            return false;
        }

        if (null === $artifact) {
            return false;
        }

        try {
            $registrar = $artifact['register'];
            $registrar($collector, $pipelineFactory);
        } catch (Throwable $error) {
            throw new RuntimeException('Failed to register compiled routes: ' . $error->getMessage(), $error->getCode(), $error);
        }

        return true;
    }

    public function hasUsableArtifact(): bool
    {
        if (! is_file($this->cacheFile)) {
            return false;
        }

        try {
            return null !== $this->cacheLoader->load($this->cacheFile, $this->configFingerprint);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param list<RouteDefinition> $routes
     */
    public function save(array $routes): bool
    {
        $content = $this->cacheGenerator->generate($routes, $this->configFingerprint);

        return $this->cacheStorage->save($this->cacheFile, $content);
    }
}
