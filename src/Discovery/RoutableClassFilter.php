<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Discovery;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionAttribute;
use ReflectionClass;
use Sirix\Mezzio\Routing\Attributes\Attribute\Route;

use function class_exists;
use function is_subclass_of;

final readonly class RoutableClassFilter
{
    public function __construct(private bool $allowMethodRouteClasses = false) {}

    /**
     * @param list<non-empty-string> $classes
     *
     * @return list<non-empty-string>
     */
    public function filter(array $classes): array
    {
        $result = [];
        foreach ($classes as $className) {
            if ($this->isPsr15Class($className)) {
                $result[] = $className;

                continue;
            }

            if ($this->allowMethodRouteClasses && $this->hasMethodRouteAttribute($className)) {
                $result[] = $className;
            }
        }

        return $result;
    }

    private function isPsr15Class(string $className): bool
    {
        return is_subclass_of($className, MiddlewareInterface::class)
            || is_subclass_of($className, RequestHandlerInterface::class);
    }

    private function hasMethodRouteAttribute(string $className): bool
    {
        if (! class_exists($className)) {
            return false;
        }

        $reflection = new ReflectionClass($className);
        foreach ($reflection->getMethods() as $method) {
            if ([] !== $method->getAttributes(Route::class, ReflectionAttribute::IS_INSTANCEOF)) {
                return true;
            }
        }

        return false;
    }
}
