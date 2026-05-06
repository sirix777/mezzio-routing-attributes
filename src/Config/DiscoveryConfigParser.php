<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Config;

use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;

use function array_key_exists;
use function array_values;
use function in_array;
use function is_array;
use function is_bool;
use function is_string;

final readonly class DiscoveryConfigParser
{
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
    public function parse(array $routingAttributesConfig): array
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
}
