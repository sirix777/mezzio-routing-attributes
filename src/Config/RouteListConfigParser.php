<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Config;

use Sirix\Mezzio\Routing\Attributes\Command\RouteMiddlewareDisplayResolver;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;

use function in_array;
use function is_array;

final readonly class RouteListConfigParser
{
    /**
     * @param array<string, mixed> $routingAttributesConfig
     *
     * @return 'resolved'|'upstream'
     */
    public function parse(array $routingAttributesConfig): string
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
}
