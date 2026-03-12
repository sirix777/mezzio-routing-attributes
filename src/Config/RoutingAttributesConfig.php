<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Config;

use Sirix\Mezzio\Routing\Attributes\AttributeRouteProvider;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveryClassMapCache;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;
use Sirix\Mezzio\Routing\Attributes\RouteDefinitionCache;

use function array_values;
use function in_array;
use function is_array;
use function is_bool;
use function is_string;

final readonly class RoutingAttributesConfig
{
    /**
     * @param list<non-empty-string>                                                                               $classes
     * @param AttributeRouteProvider::DUPLICATE_STRATEGY_IGNORE|AttributeRouteProvider::DUPLICATE_STRATEGY_THROW   $duplicateStrategy
     * @param 'callable'|'psr15'                                                                                   $handlersMode
     * @param RouteDefinitionCache::WRITE_FAIL_STRATEGY_IGNORE|RouteDefinitionCache::WRITE_FAIL_STRATEGY_THROW     $cacheWriteFailStrategy
     * @param list<non-empty-string>                                                                               $discoveryPaths
     * @param 'psr4'|'token'                                                                                       $discoveryStrategy
     * @param array<non-empty-string, non-empty-string>                                                            $discoveryPsr4Mappings
     * @param DiscoveryClassMapCache::WRITE_FAIL_STRATEGY_IGNORE|DiscoveryClassMapCache::WRITE_FAIL_STRATEGY_THROW $discoveryClassMapCacheWriteFailStrategy
     */
    public function __construct(
        public array $classes,
        public string $duplicateStrategy,
        public string $handlersMode,
        public bool $cacheEnabled,
        public ?string $cacheFile,
        public bool $cacheStrict,
        public string $cacheWriteFailStrategy,
        public bool $discoveryEnabled,
        public array $discoveryPaths,
        public string $discoveryStrategy,
        public array $discoveryPsr4Mappings,
        public bool $discoveryPsr4FallbackToToken,
        public bool $discoveryClassMapCacheEnabled,
        public ?string $discoveryClassMapCacheFile,
        public bool $discoveryClassMapCacheValidate,
        public string $discoveryClassMapCacheWriteFailStrategy
    ) {}

    public static function fromRootConfig(mixed $config): self
    {
        if (! is_array($config)) {
            throw InvalidConfigurationException::invalidConfigType($config);
        }

        $routingAttributesConfig = $config['routing_attributes'] ?? [];
        if (! is_array($routingAttributesConfig)) {
            throw InvalidConfigurationException::invalidRoutingAttributesType($routingAttributesConfig);
        }

        $normalizedClasses = self::parseClasses($routingAttributesConfig);
        $duplicateStrategy = self::parseDuplicateStrategy($routingAttributesConfig);
        $handlersMode = self::parseHandlersMode($routingAttributesConfig);
        $discovery = self::parseDiscovery($routingAttributesConfig);
        $cache = self::parseCache($routingAttributesConfig);

        return new self(
            $normalizedClasses,
            $duplicateStrategy,
            $handlersMode,
            $cache['enabled'],
            $cache['file'],
            $cache['strict'],
            $cache['writeFailStrategy'],
            $discovery['enabled'],
            $discovery['paths'],
            $discovery['strategy'],
            $discovery['psr4Mappings'],
            $discovery['psr4FallbackToToken'],
            $discovery['classMapCacheEnabled'],
            $discovery['classMapCacheFile'],
            $discovery['classMapCacheValidate'],
            $discovery['classMapCacheWriteFailStrategy']
        );
    }

    /**
     * @param array<string, mixed> $routingAttributesConfig
     *
     * @return list<non-empty-string>
     */
    private static function parseClasses(array $routingAttributesConfig): array
    {
        $classes = $routingAttributesConfig['classes'] ?? [];
        if (! is_array($classes)) {
            throw InvalidConfigurationException::invalidClassesConfiguration($classes);
        }

        foreach ($classes as $index => $className) {
            if (! is_string($className) || '' === $className) {
                throw InvalidConfigurationException::invalidClassEntry($index, $className);
            }
        }

        return array_values($classes);
    }

    /**
     * @param array<string, mixed> $routingAttributesConfig
     *
     * @return AttributeRouteProvider::DUPLICATE_STRATEGY_IGNORE|AttributeRouteProvider::DUPLICATE_STRATEGY_THROW
     */
    private static function parseDuplicateStrategy(array $routingAttributesConfig): string
    {
        $duplicateStrategy = $routingAttributesConfig['duplicate_strategy'] ?? AttributeRouteProvider::DUPLICATE_STRATEGY_THROW;
        if (
            ! in_array(
                $duplicateStrategy,
                [
                    AttributeRouteProvider::DUPLICATE_STRATEGY_THROW,
                    AttributeRouteProvider::DUPLICATE_STRATEGY_IGNORE,
                ],
                true
            )
        ) {
            throw InvalidConfigurationException::invalidDuplicateStrategy($duplicateStrategy);
        }

        return $duplicateStrategy;
    }

    /**
     * @param array<string, mixed> $routingAttributesConfig
     *
     * @return 'callable'|'psr15'
     */
    private static function parseHandlersMode(array $routingAttributesConfig): string
    {
        $handlersConfig = $routingAttributesConfig['handlers'] ?? [];
        if (! is_array($handlersConfig)) {
            throw InvalidConfigurationException::invalidHandlersType($handlersConfig);
        }

        $handlersMode = $handlersConfig['mode'] ?? 'psr15';
        if (! in_array($handlersMode, ['psr15', 'callable'], true)) {
            throw InvalidConfigurationException::invalidHandlersMode($handlersMode);
        }

        return $handlersMode;
    }

    /**
     * @param array<string, mixed> $routingAttributesConfig
     *
     * @return array{
     *     enabled: bool,
     *     paths: list<non-empty-string>,
     *     strategy: 'psr4'|'token',
     *     psr4Mappings: array<non-empty-string, non-empty-string>,
     *     psr4FallbackToToken: bool,
     *     classMapCacheEnabled: bool,
     *     classMapCacheFile: ?string,
     *     classMapCacheValidate: bool,
     *     classMapCacheWriteFailStrategy: DiscoveryClassMapCache::WRITE_FAIL_STRATEGY_IGNORE|DiscoveryClassMapCache::WRITE_FAIL_STRATEGY_THROW
     * }
     */
    private static function parseDiscovery(array $routingAttributesConfig): array
    {
        $discoveryConfig = $routingAttributesConfig['discovery'] ?? [];
        if (! is_array($discoveryConfig)) {
            throw InvalidConfigurationException::invalidDiscoveryType($discoveryConfig);
        }

        $discoveryEnabled = $discoveryConfig['enabled'] ?? false;
        if (! is_bool($discoveryEnabled)) {
            throw InvalidConfigurationException::invalidDiscoveryEnabled($discoveryEnabled);
        }

        $normalizedDiscoveryPaths = [];
        $discoveryStrategy = 'token';
        $normalizedDiscoveryPsr4Mappings = [];
        $discoveryPsr4FallbackToToken = true;
        $discoveryClassMapCacheEnabled = true;
        $discoveryClassMapCacheFile = null;
        $discoveryClassMapCacheValidate = true;
        $discoveryClassMapCacheWriteFailStrategy = DiscoveryClassMapCache::WRITE_FAIL_STRATEGY_IGNORE;

        if ($discoveryEnabled) {
            $discoveryPaths = $discoveryConfig['paths'] ?? [];
            if (! is_array($discoveryPaths)) {
                throw InvalidConfigurationException::invalidDiscoveryPaths($discoveryPaths);
            }

            foreach ($discoveryPaths as $index => $path) {
                if (! is_string($path) || '' === $path) {
                    throw InvalidConfigurationException::invalidDiscoveryPathEntry($index, $path);
                }
            }

            /** @var list<non-empty-string> $normalizedDiscoveryPaths */
            $normalizedDiscoveryPaths = array_values($discoveryPaths);

            $discoveryStrategy = $discoveryConfig['strategy'] ?? 'token';
            if (! in_array($discoveryStrategy, ['token', 'psr4'], true)) {
                throw InvalidConfigurationException::invalidDiscoveryStrategy($discoveryStrategy);
            }

            $psr4Config = $discoveryConfig['psr4'] ?? [];
            if (! is_array($psr4Config)) {
                throw InvalidConfigurationException::invalidDiscoveryPsr4Type($psr4Config);
            }

            $discoveryPsr4FallbackToToken = $psr4Config['fallback_to_token'] ?? true;
            if (! is_bool($discoveryPsr4FallbackToToken)) {
                throw InvalidConfigurationException::invalidDiscoveryPsr4FallbackToToken($discoveryPsr4FallbackToToken);
            }

            $mappings = $psr4Config['mappings'] ?? [];
            if (! is_array($mappings)) {
                throw InvalidConfigurationException::invalidDiscoveryPsr4MappingsType($mappings);
            }

            foreach ($mappings as $basePath => $namespace) {
                if (! is_string($basePath) || '' === $basePath) {
                    throw InvalidConfigurationException::invalidDiscoveryPsr4MappingPath($basePath);
                }

                if (! is_string($namespace) || '' === $namespace) {
                    throw InvalidConfigurationException::invalidDiscoveryPsr4MappingNamespace($namespace);
                }

                // @var non-empty-string $basePath
                // @var non-empty-string $namespace
                $normalizedDiscoveryPsr4Mappings[$basePath] = $namespace;
            }

            if ('psr4' === $discoveryStrategy && [] === $normalizedDiscoveryPsr4Mappings) {
                throw InvalidConfigurationException::missingDiscoveryPsr4Mappings();
            }

            $classMapCacheConfig = $discoveryConfig['class_map_cache'] ?? [];
            if (! is_array($classMapCacheConfig)) {
                throw InvalidConfigurationException::invalidDiscoveryClassMapCacheType($classMapCacheConfig);
            }

            $discoveryClassMapCacheEnabled = $classMapCacheConfig['enabled'] ?? true;
            if (! is_bool($discoveryClassMapCacheEnabled)) {
                throw InvalidConfigurationException::invalidDiscoveryClassMapCacheEnabled($discoveryClassMapCacheEnabled);
            }

            if ($discoveryClassMapCacheEnabled) {
                $configuredClassMapCacheFile = $classMapCacheConfig['file'] ?? null;
                if (! is_string($configuredClassMapCacheFile) || '' === $configuredClassMapCacheFile) {
                    throw InvalidConfigurationException::invalidDiscoveryClassMapCacheFile($configuredClassMapCacheFile);
                }

                $discoveryClassMapCacheFile = $configuredClassMapCacheFile;
            }

            $discoveryClassMapCacheValidate = $classMapCacheConfig['validate'] ?? true;
            if (! is_bool($discoveryClassMapCacheValidate)) {
                throw InvalidConfigurationException::invalidDiscoveryClassMapCacheValidate($discoveryClassMapCacheValidate);
            }

            $discoveryClassMapCacheWriteFailStrategy = $classMapCacheConfig['write_fail_strategy'] ?? DiscoveryClassMapCache::WRITE_FAIL_STRATEGY_IGNORE;
            if (
                ! in_array(
                    $discoveryClassMapCacheWriteFailStrategy,
                    [
                        DiscoveryClassMapCache::WRITE_FAIL_STRATEGY_IGNORE,
                        DiscoveryClassMapCache::WRITE_FAIL_STRATEGY_THROW,
                    ],
                    true
                )
            ) {
                throw InvalidConfigurationException::invalidDiscoveryClassMapCacheWriteFailStrategy($discoveryClassMapCacheWriteFailStrategy);
            }
        }

        return [
            'enabled' => $discoveryEnabled,
            'paths' => $normalizedDiscoveryPaths,
            'strategy' => $discoveryStrategy,
            'psr4Mappings' => $normalizedDiscoveryPsr4Mappings,
            'psr4FallbackToToken' => $discoveryPsr4FallbackToToken,
            'classMapCacheEnabled' => $discoveryClassMapCacheEnabled,
            'classMapCacheFile' => $discoveryClassMapCacheFile,
            'classMapCacheValidate' => $discoveryClassMapCacheValidate,
            'classMapCacheWriteFailStrategy' => $discoveryClassMapCacheWriteFailStrategy,
        ];
    }

    /**
     * @param array<string, mixed> $routingAttributesConfig
     *
     * @return array{
     *     enabled: bool,
     *     file: ?string,
     *     strict: bool,
     *     writeFailStrategy: RouteDefinitionCache::WRITE_FAIL_STRATEGY_IGNORE|RouteDefinitionCache::WRITE_FAIL_STRATEGY_THROW
     * }
     */
    private static function parseCache(array $routingAttributesConfig): array
    {
        $cacheConfig = $routingAttributesConfig['cache'] ?? [];
        if (! is_array($cacheConfig)) {
            throw InvalidConfigurationException::invalidCacheType($cacheConfig);
        }

        $cacheEnabled = $cacheConfig['enabled'] ?? false;
        if (! is_bool($cacheEnabled)) {
            throw InvalidConfigurationException::invalidCacheEnabled($cacheEnabled);
        }

        $cacheFile = null;
        $cacheStrict = false;
        $cacheWriteFailStrategy = RouteDefinitionCache::WRITE_FAIL_STRATEGY_IGNORE;
        if ($cacheEnabled) {
            $configuredCacheFile = $cacheConfig['file'] ?? null;
            if (! is_string($configuredCacheFile) || '' === $configuredCacheFile) {
                throw InvalidConfigurationException::invalidCacheFile($configuredCacheFile);
            }

            $cacheFile = $configuredCacheFile;

            $cacheStrict = $cacheConfig['strict'] ?? false;
            if (! is_bool($cacheStrict)) {
                throw InvalidConfigurationException::invalidCacheStrict($cacheStrict);
            }

            $cacheWriteFailStrategy = $cacheConfig['write_fail_strategy'] ?? RouteDefinitionCache::WRITE_FAIL_STRATEGY_IGNORE;
            if (
                ! in_array(
                    $cacheWriteFailStrategy,
                    [
                        RouteDefinitionCache::WRITE_FAIL_STRATEGY_IGNORE,
                        RouteDefinitionCache::WRITE_FAIL_STRATEGY_THROW,
                    ],
                    true
                )
            ) {
                throw InvalidConfigurationException::invalidCacheWriteFailStrategy($cacheWriteFailStrategy);
            }
        }

        return [
            'enabled' => $cacheEnabled,
            'file' => $cacheFile,
            'strict' => $cacheStrict,
            'writeFailStrategy' => $cacheWriteFailStrategy,
        ];
    }
}
