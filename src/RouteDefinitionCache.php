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
use function is_int;
use function is_string;
use function mkdir;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function var_export;

final readonly class RouteDefinitionCache
{
    public const CACHE_FORMAT_VERSION = 2;
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
     *     0: non-empty-string,
     *     1: null|list<non-empty-string>,
     *     2: non-empty-string,
     *     3: non-empty-string,
     *     4: list<non-empty-string>,
     *     5: null|non-empty-string
     * }>
     */
    private function serializeRoutesForCache(array $routes): array
    {
        $result = [];
        foreach ($routes as $route) {
            $result[] = [
                $route->path,
                $route->methods,
                $route->handlerService,
                $route->handlerMethod,
                $route->middlewareServices,
                $route->name,
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
        $expectedIndex = 0;
        foreach ($cachedRoutes as $index => $cachedRoute) {
            if ($index !== $expectedIndex) {
                $this->invalidCachedRoute($index, 'entry index must be a zero-based contiguous integer');

                return null;
            }
            ++$expectedIndex;

            if (! is_array($cachedRoute)) {
                $this->invalidCachedRoute($index, 'entry must be an indexed array');

                return null;
            }

            if (
                ! array_key_exists(0, $cachedRoute)
                || ! array_key_exists(1, $cachedRoute)
                || ! array_key_exists(2, $cachedRoute)
                || ! array_key_exists(3, $cachedRoute)
                || ! array_key_exists(4, $cachedRoute)
                || ! array_key_exists(5, $cachedRoute)
            ) {
                $this->invalidCachedRoute($index, 'entry must contain indexes 0..5');

                return null;
            }

            if (! is_string($cachedRoute[0]) || '' === $cachedRoute[0]) {
                $this->invalidCachedRoute($index, 'field 0 (path) must be a non-empty string');

                return null;
            }

            if (! is_string($cachedRoute[2]) || '' === $cachedRoute[2]) {
                $this->invalidCachedRoute($index, 'field 2 (handlerService) must be a non-empty string');

                return null;
            }

            if (! is_string($cachedRoute[3]) || '' === $cachedRoute[3]) {
                $this->invalidCachedRoute($index, 'field 3 (handlerMethod) must be a non-empty string');

                return null;
            }

            if (null !== $cachedRoute[1] && ! $this->isValidCachedStringList($cachedRoute[1])) {
                $this->invalidCachedRoute($index, 'field 1 (methods) must be null or a list of non-empty strings');

                return null;
            }

            if (! $this->isValidCachedStringList($cachedRoute[4])) {
                $this->invalidCachedRoute($index, 'field 4 (middlewareServices) must be a list of non-empty strings');

                return null;
            }

            if (null !== $cachedRoute[5] && (! is_string($cachedRoute[5]) || '' === $cachedRoute[5])) {
                $this->invalidCachedRoute($index, 'field 5 (name) must be null or a non-empty string');

                return null;
            }

            /** @var null|list<non-empty-string> $methods */
            $methods = $cachedRoute[1];

            /** @var list<non-empty-string> $middlewareServices */
            $middlewareServices = $cachedRoute[4];

            /** @var null|non-empty-string $name */
            $name = $cachedRoute[5];

            $routes[] = new RouteDefinition(
                $cachedRoute[0],
                $methods,
                $cachedRoute[2],
                $cachedRoute[3],
                $middlewareServices,
                $name
            );
        }

        return $routes;
    }

    private function isValidCachedStringList(mixed $value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        $expectedIndex = 0;
        foreach ($value as $index => $item) {
            if (! is_int($index) || $index !== $expectedIndex) {
                return false;
            }
            ++$expectedIndex;

            if (! is_string($item) || '' === $item) {
                return false;
            }
        }

        return true;
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
