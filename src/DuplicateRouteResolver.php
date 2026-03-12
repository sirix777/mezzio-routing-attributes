<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes;

use Sirix\Mezzio\Routing\Attributes\Exception\DuplicateRouteDefinitionException;

use function array_map;
use function implode;
use function sort;
use function strtoupper;

final readonly class DuplicateRouteResolver
{
    public const STRATEGY_THROW = 'throw';
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
        $filtered = [];
        $names = [];
        $signatures = [];

        foreach ($routes as $route) {
            if (null !== $route->name && isset($names[$route->name])) {
                if (self::STRATEGY_THROW === $this->strategy) {
                    throw DuplicateRouteDefinitionException::duplicateName($route->name);
                }

                continue;
            }

            $signature = $this->createRouteSignature($route);
            if (isset($signatures[$signature])) {
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

            $signatures[$signature] = true;
            $filtered[] = $route;
        }

        return $filtered;
    }

    private function createRouteSignature(RouteDefinition $route): string
    {
        if (null === $route->methods) {
            return $route->path . '|ANY';
        }

        $methods = array_map(strtoupper(...), $route->methods);
        sort($methods);

        return $route->path . '|' . implode(',', $methods);
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
