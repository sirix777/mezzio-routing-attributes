<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Command;

use Mezzio\Router\Route;

use function implode;

final readonly class RouteListFormatter
{
    public function __construct(private RouteMiddlewareDisplayResolver $middlewareDisplayResolver = new RouteMiddlewareDisplayResolver()) {}

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
        return $this->middlewareDisplayResolver->resolveForDisplay($route);
    }
}
