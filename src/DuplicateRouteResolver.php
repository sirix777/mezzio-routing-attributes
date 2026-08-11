<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes;

use Sirix\Mezzio\Routing\Attributes\Exception\DuplicateRouteDefinitionException;

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
            if (null !== $route->name && isset($names[$route->name])) {
                if (self::STRATEGY_THROW === $this->strategy) {
                    throw DuplicateRouteDefinitionException::duplicateName($route->name);
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

            if (null !== $route->name) {
                $names[$route->name] = true;
            }

            $routesByPath[$route->path][] = $route;
            $filtered[]                   = $route;
        }

        return $filtered;
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
