<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Command;

use Mezzio\Router\Route;
use Sirix\Mezzio\Routing\Attributes\AttributeRouteProvider;

use function array_filter;
use function array_values;
use function in_array;
use function is_string;
use function preg_match;
use function sprintf;
use function str_replace;
use function stripos;
use function strtoupper;

final class RouteListFilter
{
    /**
     * @param list<Route> $routes
     *
     * @return list<Route>
     */
    public function filter(
        array $routes,
        mixed $name,
        mixed $path,
        mixed $middleware,
        mixed $method
    ): array {
        return array_values(array_filter(
            $routes,
            function(Route $route) use ($name, $path, $middleware, $method): bool {
                if (is_string($name) && '' !== $name) {
                    if ($route->getName() === $name) {
                        return true;
                    }

                    return $this->matches($route->getName(), $name);
                }

                if (is_string($path) && '' !== $path) {
                    if ($route->getPath() === $path) {
                        return true;
                    }

                    return $this->matches($route->getPath(), $path);
                }

                if (is_string($middleware) && '' !== $middleware) {
                    $display = $this->getMiddlewareDisplay($route);

                    return $display === $middleware
                        || false !== stripos($display, $middleware)
                        || (bool) preg_match(
                            sprintf('/%s/', $this->escapeNamespaceSeparatorForRegex($middleware)),
                            $display
                        );
                }

                if (is_string($method) && '' !== $method) {
                    if ($route->allowsAnyMethod()) {
                        return true;
                    }

                    return in_array(strtoupper($method), $route->getAllowedMethods() ?? [], true);
                }

                return true;
            }
        ));
    }

    private function matches(string $subject, string $search): bool
    {
        return (bool) preg_match(
            sprintf('/^%s/', str_replace('/', '\/', $search)),
            $subject,
        );
    }

    private function escapeNamespaceSeparatorForRegex(string $toMatch): string
    {
        return str_replace('\\', '\\\\', $toMatch);
    }

    private function getMiddlewareDisplay(Route $route): string
    {
        $display = $route->getOptions()[AttributeRouteProvider::ROUTE_OPTION_MIDDLEWARE_DISPLAY] ?? null;

        return is_string($display) && '' !== $display
            ? $display
            : $route->getMiddleware()::class;
    }
}
