<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Config;

use Sirix\Mezzio\Routing\Attributes\AttributeRouteProvider;
use Sirix\Mezzio\Routing\Attributes\Command\RouteMiddlewareDisplayResolver;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;

use function array_key_exists;
use function array_values;
use function in_array;
use function is_array;
use function is_bool;
use function is_string;

final readonly class RoutingAttributesConfig
{
    /**
     * @param list<non-empty-string>                                                                             $classes
     * @param AttributeRouteProvider::DUPLICATE_STRATEGY_IGNORE|AttributeRouteProvider::DUPLICATE_STRATEGY_THROW $duplicateStrategy
     * @param 'callable'|'psr15'                                                                                 $handlersMode
     * @param 'resolved'|'upstream'                                                                              $classicRoutesMiddlewareDisplay
     * @param list<non-empty-string>                                                                             $discoveryPaths
     * @param 'psr4'|'token'                                                                                     $discoveryStrategy
     * @param array<non-empty-string, non-empty-string>                                                          $discoveryPsr4Mappings
     */
    public function __construct(
        public array $classes,
        public string $duplicateStrategy,
        public string $handlersMode,
        public string $classicRoutesMiddlewareDisplay,
        public bool $cacheEnabled,
        public ?string $cacheFile,
        public bool $discoveryEnabled,
        public array $discoveryPaths,
        public string $discoveryStrategy,
        public array $discoveryPsr4Mappings,
        public bool $discoveryPsr4FallbackToToken
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
        $classicRoutesMiddlewareDisplay = self::parseClassicRoutesMiddlewareDisplay($routingAttributesConfig);
        self::assertRemovedRootOptions($routingAttributesConfig);
        $discovery = self::parseDiscovery($routingAttributesConfig);
        $cache = self::parseCache($routingAttributesConfig);

        return new self(
            $normalizedClasses,
            $duplicateStrategy,
            $handlersMode,
            $classicRoutesMiddlewareDisplay,
            $cache['enabled'],
            $cache['file'],
            $discovery['enabled'],
            $discovery['paths'],
            $discovery['strategy'],
            $discovery['psr4Mappings'],
            $discovery['psr4FallbackToToken']
        );
    }

    /**
     * @param array<string, mixed> $routingAttributesConfig
     *
     * @return 'resolved'|'upstream'
     */
    private static function parseClassicRoutesMiddlewareDisplay(array $routingAttributesConfig): string
    {
        $routeListConfig = $routingAttributesConfig['route_list'] ?? [];
        if (! is_array($routeListConfig)) {
            throw InvalidConfigurationException::invalidRouteListType($routeListConfig);
        }

        $classicRoutesMiddlewareDisplay = $routeListConfig['classic_routes_middleware_display']
            ?? RouteMiddlewareDisplayResolver::CLASSIC_ROUTES_MIDDLEWARE_DISPLAY_UPSTREAM;

        if (
            ! in_array(
                $classicRoutesMiddlewareDisplay,
                [
                    RouteMiddlewareDisplayResolver::CLASSIC_ROUTES_MIDDLEWARE_DISPLAY_UPSTREAM,
                    RouteMiddlewareDisplayResolver::CLASSIC_ROUTES_MIDDLEWARE_DISPLAY_RESOLVED,
                ],
                true
            )
        ) {
            throw InvalidConfigurationException::invalidClassicRoutesMiddlewareDisplay($classicRoutesMiddlewareDisplay);
        }

        return $classicRoutesMiddlewareDisplay;
    }

    /**
     * @param array<string, mixed> $routingAttributesConfig
     */
    private static function assertRemovedRootOptions(array $routingAttributesConfig): void
    {
        if (array_key_exists('lazy_service_resolution', $routingAttributesConfig)) {
            throw InvalidConfigurationException::removedOption('routing_attributes.lazy_service_resolution');
        }
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
     *     psr4FallbackToToken: bool
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

                $normalizedDiscoveryPsr4Mappings[$basePath] = $namespace;
            }

            if ('psr4' === $discoveryStrategy && [] === $normalizedDiscoveryPsr4Mappings) {
                throw InvalidConfigurationException::missingDiscoveryPsr4Mappings();
            }
        }

        if (array_key_exists('class_map_cache', $discoveryConfig)) {
            throw InvalidConfigurationException::removedOption('routing_attributes.discovery.class_map_cache');
        }

        return [
            'enabled' => $discoveryEnabled,
            'paths' => $normalizedDiscoveryPaths,
            'strategy' => $discoveryStrategy,
            'psr4Mappings' => $normalizedDiscoveryPsr4Mappings,
            'psr4FallbackToToken' => $discoveryPsr4FallbackToToken,
        ];
    }

    /**
     * @param array<string, mixed> $routingAttributesConfig
     *
     * @return array{
     *     enabled: bool,
     *     file: ?string
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
        if ($cacheEnabled) {
            if (array_key_exists('mode', $cacheConfig)) {
                throw InvalidConfigurationException::removedCacheOption('mode');
            }

            if (array_key_exists('backend', $cacheConfig)) {
                throw InvalidConfigurationException::removedCacheOption('backend');
            }

            if (array_key_exists('strict', $cacheConfig)) {
                throw InvalidConfigurationException::removedCacheOption('strict');
            }

            if (array_key_exists('write_fail_strategy', $cacheConfig)) {
                throw InvalidConfigurationException::removedCacheOption('write_fail_strategy');
            }

            $configuredCacheFile = $cacheConfig['file'] ?? null;
            if (! is_string($configuredCacheFile) || '' === $configuredCacheFile) {
                throw InvalidConfigurationException::invalidCacheFile($configuredCacheFile);
            }

            $cacheFile = $configuredCacheFile;
        }

        return [
            'enabled' => $cacheEnabled,
            'file' => $cacheFile,
        ];
    }
}
