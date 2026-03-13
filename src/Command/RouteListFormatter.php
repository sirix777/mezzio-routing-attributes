<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Command;

use Mezzio\Router\Route;
use Sirix\Mezzio\Routing\Attributes\AttributeRouteProvider;

use function implode;
use function is_string;

final class RouteListFormatter
{
    /**
     * @param list<Route> $routes
     *
     * @return list<array{name: string, path: string, methods: string, middleware: string}>
     */
    public function formatRows(array $routes): array
    {
        $rows = [];

        foreach ($routes as $route) {
            $rows[] = [
                'name' => $route->getName(),
                'path' => $route->getPath(),
                'methods' => implode(',', $route->getAllowedMethods() ?? []),
                'middleware' => $this->getMiddlewareDisplay($route),
            ];
        }

        return $rows;
    }

    private function getMiddlewareDisplay(Route $route): string
    {
        $display = $route->getOptions()[AttributeRouteProvider::ROUTE_OPTION_MIDDLEWARE_DISPLAY] ?? null;

        if (is_string($display) && '' !== $display) {
            return $display;
        }

        return $route->getMiddleware()::class;
    }
}
