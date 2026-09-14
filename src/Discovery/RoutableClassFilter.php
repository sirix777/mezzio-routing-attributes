<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Discovery;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionAttribute;
use ReflectionClass;
use Sirix\Mezzio\Routing\Attributes\Attribute\Route;

use function class_exists;

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
            if (! class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);
            if ($reflection->isAbstract()) {
                continue;
            }

            if ($this->isPsr15Class($reflection)) {
                $result[] = $className;

                continue;
            }

            if ($this->allowMethodRouteClasses && $this->hasMethodRouteAttribute($reflection)) {
                $result[] = $className;
            }
        }

        return $result;
    }

    /**
     * @param ReflectionClass<object> $reflection
     */
    private function isPsr15Class(ReflectionClass $reflection): bool
    {
        return $reflection->implementsInterface(MiddlewareInterface::class)
            || $reflection->implementsInterface(RequestHandlerInterface::class);
    }

    /**
     * @param ReflectionClass<object> $reflection
     */
    private function hasMethodRouteAttribute(ReflectionClass $reflection): bool
    {
        foreach ($reflection->getMethods() as $method) {
            if ([] !== $method->getAttributes(Route::class, ReflectionAttribute::IS_INSTANCEOF)) {
                return true;
            }
        }

        return false;
    }
}
