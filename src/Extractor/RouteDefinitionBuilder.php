<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Extractor;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Sirix\Mezzio\Routing\Attributes\Attribute\Route;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidRouteDefinitionException;
use Sirix\Mezzio\Routing\Attributes\RouteDefinition;

use function is_a;
use function ltrim;
use function trim;

final readonly class RouteDefinitionBuilder
{
    public function __construct(private RouteAttributeReader $attributeReader) {}

    /**
     * @param non-empty-string $className
     * @param list<Route>      $classRoutes
     *
     * @return list<RouteDefinition>
     */
    public function buildForMethod(ReflectionMethod $method, string $className, array $classRoutes): array
    {
        return $this->buildRouteDefinitions($method, $className, $classRoutes);
    }

    /**
     * @param non-empty-string $className
     * @param list<Route>      $classRoutes
     * @param list<Route>      $methodRoutes
     *
     * @return list<RouteDefinition>
     */
    public function buildForMethodWithAttributes(
        ReflectionMethod $method,
        string $className,
        array $classRoutes,
        array $methodRoutes
    ): array {
        return $this->buildRouteDefinitions($method, $className, $classRoutes, $methodRoutes);
    }

    /**
     * @param ReflectionClass<object> $classReflection
     * @param non-empty-string        $className
     *
     * @return list<RouteDefinition>
     */
    public function buildForClass(ReflectionClass $classReflection, string $className): array
    {
        return $this->buildRouteDefinitions($classReflection, $className);
    }

    /**
     * @param ReflectionClass<object>|ReflectionMethod $reflection
     * @param non-empty-string                         $className
     * @param list<Route>                              $classRoutes
     * @param null|list<Route>                         $preloadedAttributes
     *
     * @return list<RouteDefinition>
     */
    private function buildRouteDefinitions(
        ReflectionClass|ReflectionMethod $reflection,
        string $className,
        array $classRoutes = [],
        ?array $preloadedAttributes = null
    ): array {
        $routes = [];
        $attributes = $preloadedAttributes ?? $this->attributeReader->forReflection($reflection);
        if ($reflection instanceof ReflectionMethod && [] !== $attributes) {
            $this->assertMethodRouteTarget($reflection, $className);
        }

        foreach ($attributes as $route) {
            $handlerMethod = $reflection instanceof ReflectionMethod
                ? $reflection->getName()
                : $this->resolveClassHandlerMethod($reflection);
            $path = $this->normalizePath($className, $route->path);

            if ($reflection instanceof ReflectionMethod && [] !== $classRoutes) {
                $path = $this->prependClassPrefixes($className, $path, $classRoutes);
            }

            $routes[] = new RouteDefinition(
                $path,
                $this->normalizeMethods($className, $route->methods),
                $className,
                $handlerMethod,
                $this->mergeMiddlewareServices(
                    $className,
                    $this->collectClassMiddleware($classRoutes),
                    $this->normalizeMiddlewareServices($className, $route->middleware)
                ),
                $this->normalizeName($className, $route->name)
            );
        }

        return $routes;
    }

    private function assertMethodRouteTarget(ReflectionMethod $method, string $className): void
    {
        if (! $method->isPublic()) {
            throw InvalidRouteDefinitionException::nonPublicMethod($className, $method->getName());
        }

        $parameters = $method->getParameters();
        if (! $method->isVariadic()) {
            if (0 === $method->getNumberOfParameters() || $method->getNumberOfRequiredParameters() > 1) {
                throw InvalidRouteDefinitionException::invalidMethodSignature($className, $method->getName());
            }
        } elseif ($method->getNumberOfRequiredParameters() > 1) {
            throw InvalidRouteDefinitionException::invalidMethodSignature($className, $method->getName());
        }

        if ([] !== $parameters) {
            $firstParameter = $parameters[0];
            if (! $this->supportsServerRequestType($firstParameter->getType())) {
                throw InvalidRouteDefinitionException::invalidMethodParameterType(
                    $className,
                    $method->getName(),
                    $firstParameter->getName()
                );
            }
        }

        if (! $this->supportsResponseReturnType($method->getReturnType())) {
            throw InvalidRouteDefinitionException::invalidMethodReturnType($className, $method->getName());
        }
    }

    private function supportsServerRequestType(?ReflectionType $type): bool
    {
        if (! $type instanceof ReflectionType) {
            return true;
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $part) {
                if ($this->supportsServerRequestType($part)) {
                    return true;
                }
            }

            return false;
        }

        if ($type instanceof ReflectionIntersectionType) {
            foreach ($type->getTypes() as $part) {
                if (! $part instanceof ReflectionNamedType || ! $this->supportsServerRequestNamedType($part)) {
                    return false;
                }
            }

            return true;
        }

        if (! $type instanceof ReflectionNamedType) {
            return false;
        }

        return $this->supportsServerRequestNamedType($type);
    }

    private function supportsResponseReturnType(?ReflectionType $type): bool
    {
        if (! $type instanceof ReflectionType) {
            return true;
        }

        if ($type instanceof ReflectionUnionType) {
            if ($type->allowsNull()) {
                return false;
            }

            foreach ($type->getTypes() as $part) {
                if (! $this->supportsResponseReturnType($part)) {
                    return false;
                }
            }

            return true;
        }

        if ($type instanceof ReflectionIntersectionType) {
            $hasResponseConstraint = false;
            foreach ($type->getTypes() as $part) {
                if (! $part instanceof ReflectionNamedType) {
                    return false;
                }

                if ($part->isBuiltin()) {
                    return false;
                }

                if ($this->isResponseCompatibleNamedType($part)) {
                    $hasResponseConstraint = true;
                }
            }

            return $hasResponseConstraint;
        }

        if (! $type instanceof ReflectionNamedType) {
            return false;
        }

        if ($type->allowsNull()) {
            return false;
        }

        return $this->isResponseCompatibleNamedType($type);
    }

    private function supportsServerRequestNamedType(ReflectionNamedType $type): bool
    {
        if ('mixed' === $type->getName() || 'object' === $type->getName()) {
            return true;
        }

        if ($type->isBuiltin()) {
            return false;
        }

        return is_a(ServerRequestInterface::class, $type->getName(), true);
    }

    private function isResponseCompatibleNamedType(ReflectionNamedType $type): bool
    {
        if ('mixed' === $type->getName() || 'object' === $type->getName()) {
            return true;
        }

        if ($type->isBuiltin()) {
            return false;
        }

        return is_a($type->getName(), ResponseInterface::class, true);
    }

    /**
     * @return non-empty-string
     */
    private function normalizePath(string $className, string $path): string
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
    private function prependClassPrefixes(string $className, string $path, array $classRoutes): string
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
    private function resolveClassHandlerMethod(ReflectionClass $reflection): string
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
    private function normalizeMethods(string $className, ?array $methods): ?array
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
    private function normalizeName(string $className, ?string $name): ?string
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
    private function normalizeMiddlewareServices(string $className, ?array $services): array
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
    private function collectClassMiddleware(array $classRoutes): array
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
    private function mergeMiddlewareServices(string $className, array $classMiddleware, array $routeMiddleware): array
    {
        return [
            ...$this->normalizeMiddlewareServices($className, $classMiddleware),
            ...$routeMiddleware,
        ];
    }
}
