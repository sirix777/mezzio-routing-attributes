<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes;

use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;

use function array_key_exists;
use function dirname;
use function file_put_contents;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function mkdir;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function var_export;

final readonly class RouteDefinitionCache
{
    public const CACHE_FORMAT_VERSION = 1;
    public const WRITE_FAIL_STRATEGY_IGNORE = 'ignore';
    public const WRITE_FAIL_STRATEGY_THROW = 'throw';

    /**
     * @param null|array<string, int|string> $expectedMeta
     */
    public function __construct(
        private ?string $cacheFile = null,
        private ?array $expectedMeta = null,
        private bool $strict = false,
        private string $writeFailStrategy = self::WRITE_FAIL_STRATEGY_IGNORE
    ) {}

    /**
     * @return null|list<RouteDefinition>
     */
    public function load(): ?array
    {
        if (null === $this->cacheFile || ! is_file($this->cacheFile)) {
            return null;
        }

        $cached = require $this->cacheFile;
        if (! is_array($cached)) {
            $this->invalidPayload('Top-level value must be an array.');

            return null;
        }

        $meta = [];
        $routesData = $cached;

        if (array_key_exists('meta', $cached) || array_key_exists('routes', $cached)) {
            if (! isset($cached['meta'], $cached['routes'])) {
                $this->invalidPayload('Cache envelope must contain both "meta" and "routes".');

                return null;
            }

            if (! is_array($cached['meta']) || ! is_array($cached['routes'])) {
                $this->invalidPayload('Cache envelope keys "meta" and "routes" must be arrays.');

                return null;
            }

            $meta = $cached['meta'];
            $routesData = $cached['routes'];
        }

        if (! $this->isMetaValid($meta)) {
            return null;
        }

        return $this->hydrateCachedRoutes($routesData);
    }

    /**
     * @param list<RouteDefinition> $routes
     */
    public function save(array $routes): void
    {
        if (null === $this->cacheFile) {
            return;
        }

        $directory = dirname($this->cacheFile);
        if (! is_dir($directory)) {
            $mkdirError = null;
            $mkdirResult = $this->mkdirWithCapturedError($directory, $mkdirError);
            if (! $mkdirResult && ! is_dir($directory)) {
                $this->handleWriteFailure('create directory', $mkdirError);

                return;
            }
        }

        $payload = [
            'meta' => $this->expectedMeta ?? [],
            'routes' => $this->serializeRoutesForCache($routes),
        ];
        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($payload, true) . ";\n";
        $writeError = null;
        if (false === $this->filePutContentsWithCapturedError($this->cacheFile, $content, $writeError)) {
            $this->handleWriteFailure('write', $writeError);
        }
    }

    /**
     * @param list<RouteDefinition> $routes
     *
     * @return list<array{
     *     path: non-empty-string,
     *     methods: null|list<non-empty-string>,
     *     handlerService: non-empty-string,
     *     handlerMethod: non-empty-string,
     *     middlewareServices: list<non-empty-string>,
     *     name: null|non-empty-string
     * }>
     */
    private function serializeRoutesForCache(array $routes): array
    {
        $result = [];
        foreach ($routes as $route) {
            $result[] = [
                'path' => $route->path,
                'methods' => $route->methods,
                'handlerService' => $route->handlerService,
                'handlerMethod' => $route->handlerMethod,
                'middlewareServices' => $route->middlewareServices,
                'name' => $route->name,
            ];
        }

        return $result;
    }

    /**
     * @param array<int|string, mixed> $cachedRoutes
     *
     * @return null|list<RouteDefinition>
     */
    private function hydrateCachedRoutes(array $cachedRoutes): ?array
    {
        $routes = [];
        foreach ($cachedRoutes as $index => $cachedRoute) {
            if (! is_array($cachedRoute)) {
                $this->invalidCachedRoute($index, 'entry must be an array');

                return null;
            }

            if (! isset($cachedRoute['path'], $cachedRoute['handlerService'], $cachedRoute['handlerMethod'])) {
                $this->invalidCachedRoute($index, 'required keys "path", "handlerService", "handlerMethod" are missing');

                return null;
            }

            if (! is_array($cachedRoute['middlewareServices'] ?? null)) {
                $this->invalidCachedRoute($index, 'field "middlewareServices" must be an array');

                return null;
            }

            if (! is_string($cachedRoute['path'])) {
                $this->invalidCachedRoute($index, 'field "path" must be a non-empty string');

                return null;
            }

            if ('' === $cachedRoute['path']) {
                $this->invalidCachedRoute($index, 'field "path" must be a non-empty string');

                return null;
            }

            if (! is_string($cachedRoute['handlerService'])) {
                $this->invalidCachedRoute($index, 'field "handlerService" must be a non-empty string');

                return null;
            }

            if ('' === $cachedRoute['handlerService']) {
                $this->invalidCachedRoute($index, 'field "handlerService" must be a non-empty string');

                return null;
            }

            if (! is_string($cachedRoute['handlerMethod'])) {
                $this->invalidCachedRoute($index, 'field "handlerMethod" must be a non-empty string');

                return null;
            }

            if ('' === $cachedRoute['handlerMethod']) {
                $this->invalidCachedRoute($index, 'field "handlerMethod" must be a non-empty string');

                return null;
            }

            $methods = $cachedRoute['methods'] ?? null;
            if (null !== $methods && ! is_array($methods)) {
                $this->invalidCachedRoute($index, 'field "methods" must be null or an array of non-empty strings');

                return null;
            }

            $methods = $this->normalizeCachedStringList($methods);
            if (null !== ($cachedRoute['methods'] ?? null) && null === $methods) {
                $this->invalidCachedRoute($index, 'field "methods" must contain only non-empty strings');

                return null;
            }

            $middlewareServices = $this->normalizeCachedStringList($cachedRoute['middlewareServices']);
            if (null === $middlewareServices) {
                $this->invalidCachedRoute($index, 'field "middlewareServices" must contain only non-empty strings');

                return null;
            }

            $name = $cachedRoute['name'] ?? null;
            if (null !== $name && (! is_string($name) || '' === $name)) {
                $this->invalidCachedRoute($index, 'field "name" must be null or a non-empty string');

                return null;
            }

            $routes[] = new RouteDefinition(
                $cachedRoute['path'],
                $methods,
                $cachedRoute['handlerService'],
                $cachedRoute['handlerMethod'],
                $middlewareServices,
                $name
            );
        }

        return $routes;
    }

    /** @return null|list<non-empty-string> */
    private function normalizeCachedStringList(mixed $items): ?array
    {
        if (! is_array($items)) {
            return null;
        }

        $normalized = [];
        foreach ($items as $item) {
            if (! is_string($item) || '' === $item) {
                return null;
            }

            $normalized[] = $item;
        }

        return $normalized;
    }

    /**
     * @param array<int|string, mixed> $meta
     */
    private function isMetaValid(array $meta): bool
    {
        if (null === $this->expectedMeta || [] === $this->expectedMeta) {
            return true;
        }

        foreach ($this->expectedMeta as $key => $expectedValue) {
            if (! array_key_exists($key, $meta)) {
                if ($this->strict) {
                    throw InvalidConfigurationException::staleCacheMeta($key);
                }

                return false;
            }

            if ($meta[$key] !== $expectedValue) {
                if ($this->strict) {
                    throw InvalidConfigurationException::staleCacheMeta($key);
                }

                return false;
            }
        }

        return true;
    }

    private function invalidPayload(string $reason): void
    {
        if ($this->strict) {
            throw InvalidConfigurationException::invalidCachePayload($reason);
        }
    }

    private function invalidCachedRoute(int|string $index, string $reason): void
    {
        $this->invalidPayload(sprintf(
            'Route entry "%s" is invalid: %s',
            (string) $index,
            $reason
        ));
    }

    private function handleWriteFailure(string $operation, ?string $reason = null): void
    {
        if (self::WRITE_FAIL_STRATEGY_THROW === $this->writeFailStrategy && null !== $this->cacheFile) {
            throw InvalidConfigurationException::cacheWriteFailed($this->cacheFile, $operation, $reason);
        }
    }

    private function mkdirWithCapturedError(string $directory, ?string &$error): bool
    {
        $error = null;
        set_error_handler(static function(int $severity, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });

        try {
            return mkdir($directory, 0o775, true);
        } finally {
            restore_error_handler();
        }
    }

    private function filePutContentsWithCapturedError(string $file, string $content, ?string &$error): bool|int
    {
        $error = null;
        set_error_handler(static function(int $severity, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });

        try {
            return file_put_contents($file, $content);
        } finally {
            restore_error_handler();
        }
    }
}
