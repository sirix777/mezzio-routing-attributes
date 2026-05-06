<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Extractor;

use ReflectionClass;
use Sirix\Mezzio\Routing\Attributes\Attribute\Route;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidRouteDefinitionException;

use function ltrim;
use function trim;

final readonly class RouteDataNormalizer
{
    /**
     * @return non-empty-string
     */
    public function normalizePath(string $className, string $path): string
    {
        $path = trim($path);
        if ('' === $path) {
            throw InvalidRouteDefinitionException::emptyPath($className);
        }

        return $path;
    }

    /**
     * @param list<Route>      $classRoutes
     * @param non-empty-string $path
     *
     * @return non-empty-string
     */
    public function prependClassPrefixes(string $className, string $path, array $classRoutes): string
    {
        $prefix = '';

        foreach ($classRoutes as $classRoute) {
            $classPath = $this->normalizePath($className, $classRoute->path);
            if ('/' === $classPath) {
                continue;
            }

            $prefix .= '/' . trim($classPath, '/');
        }

        if ('' === $prefix) {
            return $path;
        }

        return '/' . trim($prefix, '/') . '/' . ltrim($path, '/');
    }

    /**
     * @param ReflectionClass<object> $reflection
     *
     * @return non-empty-string
     */
    public function resolveClassHandlerMethod(ReflectionClass $reflection): string
    {
        if ($reflection->hasMethod('handle')) {
            return 'handle';
        }

        if ($reflection->hasMethod('process')) {
            return 'process';
        }

        return 'handle';
    }

    /**
     * @param null|list<string> $methods
     *
     * @return null|list<non-empty-string>
     */
    public function normalizeMethods(string $className, ?array $methods): ?array
    {
        if (null === $methods) {
            return null;
        }

        $normalized = [];
        foreach ($methods as $method) {
            $method = trim($method);
            if ('' !== $method) {
                $normalized[] = $method;
            }
        }

        if ([] === $normalized) {
            throw InvalidRouteDefinitionException::invalidMethods($className);
        }

        return $normalized;
    }

    /**
     * @return null|non-empty-string
     */
    public function normalizeName(string $className, ?string $name): ?string
    {
        if (null === $name) {
            return null;
        }

        $name = trim($name);
        if ('' === $name) {
            throw InvalidRouteDefinitionException::emptyName($className);
        }

        return $name;
    }

    /**
     * @param null|list<string> $services
     *
     * @return list<non-empty-string>
     */
    public function normalizeMiddlewareServices(string $className, ?array $services): array
    {
        if (null === $services) {
            return [];
        }

        $normalized = [];
        foreach ($services as $service) {
            $service = trim($service);
            if ('' !== $service) {
                $normalized[] = $service;
            }
        }

        if ([] === $normalized && [] !== $services) {
            throw InvalidRouteDefinitionException::invalidMiddlewareServices($className);
        }

        return $normalized;
    }

    /**
     * @param list<Route> $classRoutes
     *
     * @return list<non-empty-string>
     */
    public function collectClassMiddleware(array $classRoutes): array
    {
        $middleware = [];

        foreach ($classRoutes as $classRoute) {
            foreach ($classRoute->middleware ?? [] as $service) {
                $middleware[] = $service;
            }
        }

        return $middleware;
    }

    /**
     * @param list<non-empty-string> $classMiddleware
     * @param list<non-empty-string> $routeMiddleware
     *
     * @return list<non-empty-string>
     */
    public function mergeMiddlewareServices(string $className, array $classMiddleware, array $routeMiddleware): array
    {
        return [
            ...$this->normalizeMiddlewareServices($className, $classMiddleware),
            ...$routeMiddleware,
        ];
    }
}
