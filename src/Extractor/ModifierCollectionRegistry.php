<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Extractor;

use ReflectionClass;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;

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

        return [$collection->middlewareServices, $collection->defaults] === $legacyCollection
            ? $collection
            : null;
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
