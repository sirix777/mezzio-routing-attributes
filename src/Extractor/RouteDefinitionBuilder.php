<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Extractor;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use Sirix\Mezzio\Routing\Attributes\Attribute\Route;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidRouteDefinitionException;
use Sirix\Mezzio\Routing\Attributes\RouteDefinition;
use Sirix\Mezzio\Routing\Contracts\AggregatingRouteAttributeModifierInterface;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;
use Sirix\Mezzio\Routing\Contracts\RouteAttributeModifierInterface;

use function is_string;
use function serialize;
use function trim;

final readonly class RouteDefinitionBuilder
{
    private ModifierCollectionRegistry $modifierCollectionRegistry;

    public function __construct(
        private RouteAttributeReader $attributeReader,
        private MethodSignatureValidator $methodSignatureValidator,
        private RouteDataNormalizer $routeDataNormalizer,
        ?ModifierCollectionRegistry $modifierCollectionRegistry = null
    ) {
        $this->modifierCollectionRegistry = $modifierCollectionRegistry ?? new ModifierCollectionRegistry();
    }

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
     * @param non-empty-string                                                                                    $className
     * @param list<Route>                                                                                         $classRoutes
     * @param list<Route>                                                                                         $methodRoutes
     * @param null|array{list<MiddlewareSpecification|non-empty-string>, array<string, mixed>}|ModifierCollection $classModifiers
     *
     * @return list<RouteDefinition>
     */
    public function buildForMethodWithAttributes(
        ReflectionMethod $method,
        string $className,
        array $classRoutes,
        array $methodRoutes,
        array|ModifierCollection|null $classModifiers = null
    ): array {
        return $this->buildRouteDefinitions($method, $className, $classRoutes, $methodRoutes, $classModifiers);
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
     * @param ReflectionClass<object> $classReflection
     * @param non-empty-string        $className
     *
     * @return array{list<MiddlewareSpecification|non-empty-string>, array<string, mixed>}
     */
    public function collectClassModifiers(ReflectionClass $classReflection, string $className): array
    {
        $collection = $this->collectClassModifierCollection($classReflection, $className);

        return [$collection->middlewareServices, $collection->defaults];
    }

    /**
     * Retains unique middleware identity keys so a method-level modifier can
     * deduplicate against a class-level modifier.
     *
     * @param ReflectionClass<object> $classReflection
     * @param non-empty-string        $className
     */
    public function collectClassModifierCollection(ReflectionClass $classReflection, string $className): ModifierCollection
    {
        $collection = $this->collectModifiers($classReflection, $className);
        $this->modifierCollectionRegistry->remember($classReflection, $className, $collection);

        return $collection;
    }

    /**
     * @param ReflectionClass<object>|ReflectionMethod                                                            $reflection
     * @param non-empty-string                                                                                    $className
     * @param list<Route>                                                                                         $classRoutes
     * @param null|list<Route>                                                                                    $preloadedAttributes
     * @param null|array{list<MiddlewareSpecification|non-empty-string>, array<string, mixed>}|ModifierCollection $preloadedClassModifiers
     *
     * @return list<RouteDefinition>
     */
    private function buildRouteDefinitions(
        ReflectionClass|ReflectionMethod $reflection,
        string $className,
        array $classRoutes = [],
        ?array $preloadedAttributes = null,
        array|ModifierCollection|null $preloadedClassModifiers = null
    ): array {
        $routes     = [];
        $attributes = $preloadedAttributes ?? $this->attributeReader->forReflection($reflection);
        if ($reflection instanceof ReflectionMethod && [] !== $attributes) {
            $this->methodSignatureValidator->validate($reflection, $className);
        }

        if ($reflection instanceof ReflectionMethod) {
            $classModifiers = $this->normalizeClassModifiers(
                $preloadedClassModifiers,
                $reflection->getDeclaringClass(),
                $className
            );
            $modifiers = $this->collectModifiers($reflection, $className, $classModifiers);
        } else {
            $modifiers = $this->collectModifiers($reflection, $className);
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
                    ...$modifiers->middlewareServices,
                ],
                $this->routeDataNormalizer->normalizeName($className, $route->name),
                $modifiers->defaults
            );
        }

        return $routes;
    }

    /**
     * @param ReflectionClass<object>|ReflectionMethod $reflection
     * @param non-empty-string                         $className
     */
    private function collectModifiers(
        ReflectionClass|ReflectionMethod $reflection,
        string $className,
        ?ModifierCollection $initialCollection = null
    ): ModifierCollection {
        $middleware               = [];
        $defaults                 = [];
        $uniqueMiddlewareServices = [];
        if ($initialCollection instanceof ModifierCollection) {
            $middleware               = $initialCollection->middlewareServices;
            $defaults                 = $initialCollection->defaults;
            $uniqueMiddlewareServices = $initialCollection->uniqueMiddlewareServices;
        }

        foreach ($reflection->getAttributes(RouteAttributeModifierInterface::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            $instance = $attribute->newInstance();

            foreach ($instance->getMiddleware() as $service) {
                $middleware[] = $service;
            }

            if (! $instance instanceof AggregatingRouteAttributeModifierInterface) {
                $defaults = [...$defaults, ...$instance->getDefaults()];

                continue;
            }

            $defaults = $instance->mergeDefaults($defaults);
            foreach ($instance->getUniqueMiddleware() as $key => $service) {
                $key     = $this->normalizeUniqueMiddlewareKey($className, $key);
                $service = $this->normalizeUniqueMiddlewareService($className, $service);
                if (isset($uniqueMiddlewareServices[$key])) {
                    if ($this->middlewareIdentity($uniqueMiddlewareServices[$key]) !== $this->middlewareIdentity($service)) {
                        throw InvalidRouteDefinitionException::conflictingUniqueMiddleware($className, $key);
                    }

                    continue;
                }

                $uniqueMiddlewareServices[$key] = $service;
                $middleware[]                   = $service;
            }
        }

        return new ModifierCollection(
            $this->routeDataNormalizer->normalizeMiddlewareServices($className, $middleware),
            $defaults,
            $uniqueMiddlewareServices
        );
    }

    /**
     * @param null|array{list<MiddlewareSpecification|non-empty-string>, array<string, mixed>}|ModifierCollection $preloadedClassModifiers
     * @param ReflectionClass<object>                                                                             $classReflection
     * @param non-empty-string                                                                                    $className
     */
    private function normalizeClassModifiers(
        array|ModifierCollection|null $preloadedClassModifiers,
        ReflectionClass $classReflection,
        string $className
    ): ModifierCollection {
        if ($preloadedClassModifiers instanceof ModifierCollection) {
            return $preloadedClassModifiers;
        }

        if (null === $preloadedClassModifiers) {
            return $this->collectModifiers($classReflection, $className);
        }

        return $this->modifierCollectionRegistry->find($classReflection, $className, $preloadedClassModifiers)
            ?? new ModifierCollection($preloadedClassModifiers[0], $preloadedClassModifiers[1], []);
    }

    /**
     * @return non-empty-string
     */
    private function normalizeUniqueMiddlewareKey(string $className, mixed $key): string
    {
        if (! is_string($key)) {
            throw InvalidRouteDefinitionException::invalidUniqueMiddlewareKey($className);
        }

        $key = trim($key);
        if ('' === $key) {
            throw InvalidRouteDefinitionException::invalidUniqueMiddlewareKey($className);
        }

        return $key;
    }

    /**
     * @return MiddlewareSpecification|non-empty-string
     */
    private function normalizeUniqueMiddlewareService(string $className, mixed $service): MiddlewareSpecification|string
    {
        return $this->routeDataNormalizer->normalizeMiddlewareServices($className, [$service])[0];
    }

    /** @param MiddlewareSpecification|non-empty-string $service */
    private function middlewareIdentity(MiddlewareSpecification|string $service): string
    {
        return serialize($service instanceof MiddlewareSpecification
            ? ['specification', [$service->service, $service->factory, $service->arguments]]
            : ['service', $service]);
    }
}
