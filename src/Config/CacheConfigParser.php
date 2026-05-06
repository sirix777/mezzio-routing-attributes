<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Config;

use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;

use function array_key_exists;
use function is_array;
use function is_bool;
use function is_string;

final readonly class CacheConfigParser
{
    /**
     * @param array<string, mixed> $routingAttributesConfig
     *
     * @return array{
     *     enabled: bool,
     *     file: ?string
     * }
     */
    public function parse(array $routingAttributesConfig): array
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
