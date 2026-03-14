<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Command;

use Mezzio\Router\Route;
use ReflectionException;
use ReflectionObject;

use function get_object_vars;
use function is_string;
use function property_exists;

final readonly class RouteMiddlewareDisplayResolver
{
    public const CLASSIC_ROUTES_MIDDLEWARE_DISPLAY_UPSTREAM = 'upstream';
    public const CLASSIC_ROUTES_MIDDLEWARE_DISPLAY_RESOLVED = 'resolved';
    public const ROUTE_OPTION_MIDDLEWARE_DISPLAY = 'sirix_routing_attributes.middleware_display';

    /**
     * @param self::CLASSIC_ROUTES_MIDDLEWARE_DISPLAY_RESOLVED|self::CLASSIC_ROUTES_MIDDLEWARE_DISPLAY_UPSTREAM $classicRoutesMiddlewareDisplay
     */
    public function __construct(private string $classicRoutesMiddlewareDisplay = self::CLASSIC_ROUTES_MIDDLEWARE_DISPLAY_UPSTREAM) {}

    public function resolveForDisplay(Route $route): string
    {
        return $this->resolveMiddlewareDisplay($route);
    }

    public function resolveForFilter(Route $route): string
    {
        return $this->resolveMiddlewareDisplay($route);
    }

    private function resolveMiddlewareDisplay(Route $route): string
    {
        $attributeDisplay = $route->getOptions()[self::ROUTE_OPTION_MIDDLEWARE_DISPLAY] ?? null;
        if (is_string($attributeDisplay) && '' !== $attributeDisplay) {
            return $attributeDisplay;
        }

        return $this->resolveClassicRouteMiddleware($route);
    }

    private function resolveClassicRouteMiddleware(Route $route): string
    {
        $middleware = $route->getMiddleware();
        if (self::CLASSIC_ROUTES_MIDDLEWARE_DISPLAY_RESOLVED === $this->classicRoutesMiddlewareDisplay) {
            $middlewareName = get_object_vars($middleware)['middlewareName'] ?? null;
            if (! is_string($middlewareName) || '' === $middlewareName) {
                $middlewareName = $this->readMiddlewareNameViaReflection($middleware);
            }

            if (is_string($middlewareName) && '' !== $middlewareName) {
                return $middlewareName;
            }
        }

        return $middleware::class;
    }

    private function readMiddlewareNameViaReflection(object $middleware): ?string
    {
        if (! property_exists($middleware, 'middlewareName')) {
            return null;
        }

        try {
            $reflection = new ReflectionObject($middleware);
            $property = $reflection->getProperty('middlewareName');
            $value = $property->getValue($middleware);

            return is_string($value) && '' !== $value ? $value : null;
        } catch (ReflectionException) {
            return null;
        }
    }
}
