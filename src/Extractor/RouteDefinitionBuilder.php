<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Extractor;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use Sirix\Mezzio\Routing\Attributes\Attribute\Route;
use Sirix\Mezzio\Routing\Attributes\Contract\RouteAttributeModifierInterface;
use Sirix\Mezzio\Routing\Attributes\RouteDefinition;

final readonly class RouteDefinitionBuilder
{
    public function __construct(
        private RouteAttributeReader $attributeReader,
        private MethodSignatureValidator $methodSignatureValidator,
        private RouteDataNormalizer $routeDataNormalizer
    ) {}

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
            $this->methodSignatureValidator->validate($reflection, $className);
        }

        [$modifierMiddleware, $modifierDefaults] = $this->collectModifiers($reflection, $className);
        if ($reflection instanceof ReflectionMethod) {
            $classReflection = $reflection->getDeclaringClass();
            [$classModifierMiddleware, $classModifierDefaults] = $this->collectModifiers(
                $classReflection,
                $className
            );

            $modifierMiddleware = [
                ...$classModifierMiddleware,
                ...$modifierMiddleware,
            ];
            $modifierDefaults = [
                ...$classModifierDefaults,
                ...$modifierDefaults,
            ];
        }

        foreach ($attributes as $route) {
            $handlerMethod = $reflection instanceof ReflectionMethod
                ? $reflection->getName()
                : $this->routeDataNormalizer->resolveClassHandlerMethod($reflection);
            $path = $this->routeDataNormalizer->normalizePath($className, $route->path);

            if ($reflection instanceof ReflectionMethod && [] !== $classRoutes) {
                $path = $this->routeDataNormalizer->prependClassPrefixes($className, $path, $classRoutes);
            }

            $routeMiddleware = $this->routeDataNormalizer->mergeMiddlewareServices(
                $className,
                $this->routeDataNormalizer->collectClassMiddleware($classRoutes),
                $this->routeDataNormalizer->normalizeMiddlewareServices($className, $route->middleware)
            );

            $routes[] = new RouteDefinition(
                $path,
                $this->routeDataNormalizer->normalizeMethods($className, $route->methods),
                $className,
                $handlerMethod,
                [
                    ...$routeMiddleware,
                    ...$modifierMiddleware,
                ],
                $this->routeDataNormalizer->normalizeName($className, $route->name),
                $modifierDefaults
            );
        }

        return $routes;
    }

    /**
     * @param ReflectionClass<object>|ReflectionMethod $reflection
     * @param non-empty-string                         $className
     *
     * @return array{list<non-empty-string>, array<string, mixed>}
     */
    private function collectModifiers(ReflectionClass|ReflectionMethod $reflection, string $className): array
    {
        $middleware = [];
        $defaults = [];

        foreach ($reflection->getAttributes(RouteAttributeModifierInterface::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            $instance = $attribute->newInstance();

            foreach ($instance->getMiddleware() as $service) {
                $middleware[] = $service;
            }

            $defaults = [...$defaults, ...$instance->getDefaults()];
        }

        return [$this->routeDataNormalizer->normalizeMiddlewareServices($className, $middleware), $defaults];
    }
}
