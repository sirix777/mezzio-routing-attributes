<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Exception;

use InvalidArgumentException;
use Mezzio\Router\RouteCollectorInterface;

use function get_debug_type;
use function sprintf;

class InvalidConfigurationException extends InvalidArgumentException
{
    public static function invalidConfigType(mixed $config): self
    {
        return new self(sprintf(
            'Configuration service "config" must be an array; received %s.',
            get_debug_type($config)
        ));
    }

    public static function invalidRoutingAttributesType(mixed $routingAttributesConfig): self
    {
        return new self(sprintf(
            'Configuration key "routing_attributes" must be an array; received %s.',
            get_debug_type($routingAttributesConfig)
        ));
    }

    public static function invalidClassesConfiguration(mixed $classes): self
    {
        return new self(sprintf(
            'Configuration key "routing_attributes.classes" must be an array; received %s.',
            get_debug_type($classes)
        ));
    }

    public static function invalidClassEntry(int|string $index, mixed $className): self
    {
        return new self(sprintf(
            'Configuration key "routing_attributes.classes" must contain non-empty strings; received %s at index "%s".',
            get_debug_type($className),
            (string) $index
        ));
    }

    public static function invalidDuplicateStrategy(mixed $strategy): self
    {
        return new self(sprintf(
            'Configuration key "routing_attributes.duplicate_strategy" must be one of "throw" or "ignore"; received %s.',
            get_debug_type($strategy)
        ));
    }

    public static function invalidHandlersType(mixed $handlers): self
    {
        return new self(sprintf(
            'Configuration key "routing_attributes.handlers" must be an array; received %s.',
            get_debug_type($handlers)
        ));
    }

    public static function invalidHandlersMode(mixed $mode): self
    {
        return new self(sprintf(
            'Configuration key "routing_attributes.handlers.mode" must be one of "psr15" or "callable"; received %s.',
            get_debug_type($mode)
        ));
    }

    public static function invalidRouteListType(mixed $routeList): self
    {
        return new self(sprintf(
            'Configuration key "routing_attributes.route_list" must be an array; received %s.',
            get_debug_type($routeList)
        ));
    }

    public static function invalidClassicRoutesMiddlewareDisplay(mixed $display): self
    {
        return new self(sprintf(
            'Configuration key "routing_attributes.route_list.classic_routes_middleware_display" must be one of "upstream" or "resolved"; received %s.',
            get_debug_type($display)
        ));
    }

    public static function invalidCacheType(mixed $cacheConfig): self
    {
        return new self(sprintf(
            'Configuration key "routing_attributes.cache" must be an array; received %s.',
            get_debug_type($cacheConfig)
        ));
    }

    public static function invalidCacheEnabled(mixed $enabled): self
    {
        return new self(sprintf(
            'Configuration key "routing_attributes.cache.enabled" must be a boolean; received %s.',
            get_debug_type($enabled)
        ));
    }

    public static function invalidCacheFile(mixed $file): self
    {
        return new self(sprintf(
            'Configuration key "routing_attributes.cache.file" must be a non-empty string when cache is enabled; received %s.',
            get_debug_type($file)
        ));
    }

    public static function removedCacheOption(string $option): self
    {
        return new self(sprintf(
            'Configuration key "routing_attributes.cache.%s" is no longer supported. Use compiled cache defaults.',
            $option
        ));
    }

    public static function removedOption(string $option): self
    {
        return new self(sprintf(
            'Configuration key "%s" is no longer supported in performance-first mode.',
            $option
        ));
    }

    public static function invalidDiscoveryType(mixed $discovery): self
    {
        return new self(sprintf(
            'Configuration key "routing_attributes.discovery" must be an array; received %s.',
            get_debug_type($discovery)
        ));
    }

    public static function invalidDiscoveryEnabled(mixed $enabled): self
    {
        return new self(sprintf(
            'Configuration key "routing_attributes.discovery.enabled" must be a boolean; received %s.',
            get_debug_type($enabled)
        ));
    }

    public static function invalidDiscoveryPaths(mixed $paths): self
    {
        return new self(sprintf(
            'Configuration key "routing_attributes.discovery.paths" must be an array; received %s.',
            get_debug_type($paths)
        ));
    }

    public static function invalidDiscoveryStrategy(mixed $strategy): self
    {
        return new self(sprintf(
            'Configuration key "routing_attributes.discovery.strategy" must be one of "token" or "psr4"; received %s.',
            get_debug_type($strategy)
        ));
    }

    public static function invalidDiscoveryPsr4Type(mixed $psr4): self
    {
        return new self(sprintf(
            'Configuration key "routing_attributes.discovery.psr4" must be an array; received %s.',
            get_debug_type($psr4)
        ));
    }

    public static function invalidDiscoveryPsr4FallbackToToken(mixed $fallbackToToken): self
    {
        return new self(sprintf(
            'Configuration key "routing_attributes.discovery.psr4.fallback_to_token" must be a boolean; received %s.',
            get_debug_type($fallbackToToken)
        ));
    }

    public static function missingDiscoveryPsr4Mappings(): self
    {
        return new self(
            'Configuration key "routing_attributes.discovery.psr4.mappings" must be a non-empty array when strategy is "psr4".'
        );
    }

    public static function invalidDiscoveryPsr4MappingsType(mixed $mappings): self
    {
        return new self(sprintf(
            'Configuration key "routing_attributes.discovery.psr4.mappings" must be an array; received %s.',
            get_debug_type($mappings)
        ));
    }

    public static function invalidDiscoveryPsr4MappingPath(mixed $path): self
    {
        return new self(sprintf(
            'Configuration key "routing_attributes.discovery.psr4.mappings" must contain non-empty string paths as keys; received %s.',
            get_debug_type($path)
        ));
    }

    public static function invalidDiscoveryPsr4MappingNamespace(mixed $namespace): self
    {
        return new self(sprintf(
            'Configuration key "routing_attributes.discovery.psr4.mappings" must contain non-empty string namespaces as values; received %s.',
            get_debug_type($namespace)
        ));
    }

    public static function invalidDiscoveryPathEntry(int|string $index, mixed $path): self
    {
        return new self(sprintf(
            'Configuration key "routing_attributes.discovery.paths" must contain non-empty strings; received %s at index "%s".',
            get_debug_type($path),
            (string) $index
        ));
    }

    public static function invalidCachePayload(string $reason): self
    {
        return new self(sprintf(
            'Route cache payload is invalid: %s.',
            $reason
        ));
    }

    public static function invalidRouteCollectorCallbackReturn(mixed $collector): self
    {
        return new self(sprintf(
            'Route collector delegator expected callback to return %s; received %s.',
            RouteCollectorInterface::class,
            get_debug_type($collector)
        ));
    }
}
