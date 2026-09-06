<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes;

use Mezzio\Router\Route;
use Sirix\Mezzio\Routing\Attributes\Exception\DuplicateRouteDefinitionException;

use function array_map;
use function implode;
use function strtoupper;

final readonly class DuplicateRouteResolver
{
    public const STRATEGY_THROW  = 'throw';
    public const STRATEGY_IGNORE = 'ignore';

    /**
     * @param self::STRATEGY_IGNORE|self::STRATEGY_THROW $strategy
     */
    public function __construct(private string $strategy = self::STRATEGY_THROW) {}

    /**
     * @param list<RouteDefinition> $routes
     *
     * @return list<RouteDefinition>
     */
    public function resolve(array $routes): array
    {
        $filtered     = [];
        $names        = [];
        $routesByPath = [];

        foreach ($routes as $route) {
            $name = $this->effectiveName($route);
            if (isset($names[$name])) {
                if (self::STRATEGY_THROW === $this->strategy) {
                    throw DuplicateRouteDefinitionException::duplicateName($name);
                }

                continue;
            }

            if ($this->hasOverlappingRoute($route, $routesByPath[$route->path] ?? [])) {
                if (self::STRATEGY_THROW === $this->strategy) {
                    throw DuplicateRouteDefinitionException::duplicatePathAndMethods(
                        $route->path,
                        $this->methodsToDebugString($route->methods)
                    );
                }

                continue;
            }

            $names[$name] = true;

            $routesByPath[$route->path][] = $route;
            $filtered[]                   = $route;
        }

        return $filtered;
    }

    private function effectiveName(RouteDefinition $route): string
    {
        $name = RouteRegistrar::normalizeRouteName($route->name);
        if (null !== $name) {
            return $name;
        }

        // Mezzio preserves method order and uppercases methods before generating the name.
        return null === $route->methods
            ? $route->path
            : $route->path . '^' . implode(Route::HTTP_METHOD_SEPARATOR, array_map(strtoupper(...), $route->methods));
    }

    /**
     * @param list<RouteDefinition> $existingRoutes
     */
    private function hasOverlappingRoute(RouteDefinition $route, array $existingRoutes): bool
    {
        foreach ($existingRoutes as $existingRoute) {
            if ($this->methodsOverlap($route->methods, $existingRoute->methods)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param null|list<non-empty-string> $leftMethods
     * @param null|list<non-empty-string> $rightMethods
     */
    private function methodsOverlap(?array $leftMethods, ?array $rightMethods): bool
    {
        if (null === $leftMethods || null === $rightMethods) {
            return true;
        }

        foreach ($leftMethods as $leftMethod) {
            foreach ($rightMethods as $rightMethod) {
                if (strtoupper($leftMethod) === strtoupper($rightMethod)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param null|list<non-empty-string> $methods
     */
    private function methodsToDebugString(?array $methods): string
    {
        if (null === $methods) {
            return 'ANY';
        }

        return implode(',', $methods);
    }
}
