<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Command;

use Mezzio\Router\Route;
use Sirix\Mezzio\Routing\Attributes\AttributeRouteProvider;

use function get_object_vars;
use function is_string;

final readonly class RouteMiddlewareDisplayResolver
{
    public const CLASSIC_ROUTES_MIDDLEWARE_DISPLAY_UPSTREAM = 'upstream';
    public const CLASSIC_ROUTES_MIDDLEWARE_DISPLAY_RESOLVED = 'resolved';

    /**
     * @param self::CLASSIC_ROUTES_MIDDLEWARE_DISPLAY_RESOLVED|self::CLASSIC_ROUTES_MIDDLEWARE_DISPLAY_UPSTREAM $classicRoutesMiddlewareDisplay
     */
    public function __construct(private string $classicRoutesMiddlewareDisplay = self::CLASSIC_ROUTES_MIDDLEWARE_DISPLAY_UPSTREAM) {}

    public function resolveForDisplay(Route $route): string
    {
        $attributeDisplay = $route->getOptions()[AttributeRouteProvider::ROUTE_OPTION_MIDDLEWARE_DISPLAY] ?? null;
        if (is_string($attributeDisplay) && '' !== $attributeDisplay) {
            return $attributeDisplay;
        }

        return $this->resolveClassicRouteMiddleware($route);
    }

    public function resolveForFilter(Route $route): string
    {
        $attributeDisplay = $route->getOptions()[AttributeRouteProvider::ROUTE_OPTION_MIDDLEWARE_DISPLAY] ?? null;
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
            if (is_string($middlewareName) && '' !== $middlewareName) {
                return $middlewareName;
            }
        }

        return $middleware::class;
    }
}
