<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Config;

use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;

use function array_key_exists;
use function is_array;

final readonly class RoutingAttributesConfig
{
    /**
     * @param list<non-empty-string>                    $classes
     * @param 'ignore'|'throw'                          $duplicateStrategy
     * @param 'callable'|'psr15'                        $handlersMode
     * @param 'resolved'|'upstream'                     $classicRoutesMiddlewareDisplay
     * @param list<non-empty-string>                    $discoveryPaths
     * @param 'psr4'|'token'                            $discoveryStrategy
     * @param array<non-empty-string, non-empty-string> $discoveryPsr4Mappings
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

        self::assertRemovedRootOptions($routingAttributesConfig);

        $classes = (new ClassesConfigParser())->parse($routingAttributesConfig);
        $duplicateStrategy = (new DuplicateStrategyConfigParser())->parse($routingAttributesConfig);
        $handlersMode = (new HandlersConfigParser())->parse($routingAttributesConfig);
        $classicRoutesMiddlewareDisplay = (new RouteListConfigParser())->parse($routingAttributesConfig);
        $discovery = (new DiscoveryConfigParser())->parse($routingAttributesConfig);
        $cache = (new CacheConfigParser())->parse($routingAttributesConfig);

        return new self(
            $classes,
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
     */
    private static function assertRemovedRootOptions(array $routingAttributesConfig): void
    {
        if (array_key_exists('lazy_service_resolution', $routingAttributesConfig)) {
            throw InvalidConfigurationException::removedOption('routing_attributes.lazy_service_resolution');
        }
    }
}
