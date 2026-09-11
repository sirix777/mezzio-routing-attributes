<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Extractor;

use ReflectionClass;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;

use function count;
use function is_string;

/** @internal */
final class ModifierCollectionRegistry
{
    /** @var array<string, ModifierCollection> */
    private array $collections = [];

    /**
     * @param ReflectionClass<object> $classReflection
     * @param non-empty-string        $className
     */
    public function remember(ReflectionClass $classReflection, string $className, ModifierCollection $collection): void
    {
        $this->collections[$this->key($classReflection, $className)] = $collection;
    }

    /**
     * @param ReflectionClass<object>                                                     $classReflection
     * @param non-empty-string                                                            $className
     * @param array{list<MiddlewareSpecification|non-empty-string>, array<string, mixed>} $legacyCollection
     */
    public function find(ReflectionClass $classReflection, string $className, array $legacyCollection): ?ModifierCollection
    {
        $collection = $this->collections[$this->key($classReflection, $className)] ?? null;
        if (null === $collection) {
            return null;
        }

        return $this->matchesLegacyCollection($collection, $legacyCollection)
            ? $collection
            : null;
    }

    /**
     * @param array{list<MiddlewareSpecification|non-empty-string>, array<string, mixed>} $legacyCollection
     */
    private function matchesLegacyCollection(ModifierCollection $collection, array $legacyCollection): bool
    {
        if ($collection->defaults !== $legacyCollection[1]
            || count($collection->middlewareServices) !== count($legacyCollection[0])) {
            return false;
        }

        foreach ($collection->middlewareServices as $index => $middleware) {
            $legacyMiddleware = $legacyCollection[0][$index];
            if ($middleware instanceof MiddlewareSpecification && $legacyMiddleware instanceof MiddlewareSpecification) {
                if ($middleware->signature() !== $legacyMiddleware->signature()) {
                    return false;
                }

                continue;
            }

            if (! is_string($middleware) || ! is_string($legacyMiddleware) || $middleware !== $legacyMiddleware) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param ReflectionClass<object> $classReflection
     * @param non-empty-string        $className
     */
    private function key(ReflectionClass $classReflection, string $className): string
    {
        return $classReflection->getName() . "\0" . $className;
    }
}
