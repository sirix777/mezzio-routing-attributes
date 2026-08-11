<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Command;

use Mezzio\Router\Route;

use function array_filter;
use function array_values;
use function in_array;
use function is_string;
use function preg_match;
use function preg_quote;
use function sprintf;
use function stripos;
use function strtoupper;

final readonly class RouteListFilter
{
    public function __construct(private RouteMiddlewareDisplayResolver $middlewareDisplayResolver) {}

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
                    if (! $this->matches($route->getName(), $name)) {
                        return false;
                    }
                }

                if (is_string($path) && '' !== $path) {
                    if (! $this->matches($route->getPath(), $path)) {
                        return false;
                    }
                }

                if (is_string($middleware) && '' !== $middleware) {
                    $middlewareClass = $this->middlewareDisplayResolver->resolveForFilter($route);

                    if (false === stripos($middlewareClass, $middleware)) {
                        return false;
                    }
                }

                if (is_string($method) && '' !== $method) {
                    if ($route->allowsAnyMethod()) {
                        return true;
                    }

                    if (! in_array(strtoupper($method), $route->getAllowedMethods() ?? [], true)) {
                        return false;
                    }
                }

                return true;
            }
        ));
    }

    private function matches(string $subject, string $search): bool
    {
        return (bool) preg_match(
            sprintf('/^%s/', preg_quote($search, '/')),
            $subject,
        );
    }
}
