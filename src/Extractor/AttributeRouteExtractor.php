<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Extractor;

use ReflectionClass;
use ReflectionMethod;
use Sirix\Mezzio\Routing\Attributes\Attribute\Route;
use Sirix\Mezzio\Routing\Attributes\RouteDefinition;

final readonly class AttributeRouteExtractor implements AttributeRouteExtractorInterface
{
    public function __construct(
        private ?ClassEligibilityValidator $classEligibilityValidator = null,
        private ?RouteAttributeReader $routeAttributeReader = null,
        private ?RouteDefinitionBuilder $routeDefinitionBuilder = null
    ) {}

    /**
     * @param list<string> $classes
     *
     * @return list<RouteDefinition>
     */
    public function extract(array $classes): array
    {
        $routes = [];

        foreach ($classes as $index => $className) {
            $this->classEligibilityValidator()->assertClassExists($className, $index);

            /** @var class-string<object> $className */
            $reflection = new ReflectionClass($className);
            $classRoutes = $this->routeAttributeReader()->forReflection($reflection);
            $methodRoutes = [];

            /** @var list<array{method: ReflectionMethod, attributes: list<Route>}> $methodsWithRouteAttributes */
            $methodsWithRouteAttributes = [];

            foreach ($reflection->getMethods() as $method) {
                $methodAttributes = $this->routeAttributeReader()->forReflection($method);
                if ([] === $methodAttributes) {
                    continue;
                }

                $methodsWithRouteAttributes[] = [
                    'method' => $method,
                    'attributes' => $methodAttributes,
                ];
            }

            $this->classEligibilityValidator()->assertMiddlewareClass($className, [] !== $methodsWithRouteAttributes);

            foreach ($methodsWithRouteAttributes as $entry) {
                foreach ($this->routeDefinitionBuilder()->buildForMethodWithAttributes(
                    $entry['method'],
                    $className,
                    $classRoutes,
                    $entry['attributes']
                ) as $route) {
                    $methodRoutes[] = $route;
                }
            }

            if ([] !== $methodRoutes) {
                foreach ($methodRoutes as $route) {
                    $routes[] = $route;
                }

                continue;
            }

            foreach ($this->routeDefinitionBuilder()->buildForClass($reflection, $className) as $route) {
                $routes[] = $route;
            }
        }

        return $routes;
    }

    private function classEligibilityValidator(): ClassEligibilityValidator
    {
        return $this->classEligibilityValidator ?? new ClassEligibilityValidator();
    }

    private function routeAttributeReader(): RouteAttributeReader
    {
        return $this->routeAttributeReader ?? new RouteAttributeReader();
    }

    private function routeDefinitionBuilder(): RouteDefinitionBuilder
    {
        return $this->routeDefinitionBuilder
            ?? new RouteDefinitionBuilder($this->routeAttributeReader());
    }
}
