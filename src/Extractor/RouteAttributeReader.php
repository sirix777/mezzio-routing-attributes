<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Extractor;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use Sirix\Mezzio\Routing\Attributes\Attribute\Route;

final class RouteAttributeReader
{
    /**
     * @param ReflectionClass<object>|ReflectionMethod $reflection
     *
     * @return list<Route>
     */
    public function forReflection(ReflectionClass|ReflectionMethod $reflection): array
    {
        $attributes = [];

        foreach ($reflection->getAttributes(Route::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            /** @var Route $route */
            $route = $attribute->newInstance();
            $attributes[] = $route;
        }

        return $attributes;
    }
}
