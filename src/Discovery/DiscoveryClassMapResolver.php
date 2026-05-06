<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Discovery;

use function array_keys;
use function sort;
use function usort;

final readonly class DiscoveryClassMapResolver implements DiscoveredClassesResolverInterface
{
    /**
     * @param 'psr4'|'token' $strategy
     */
    public function __construct(
        private string $strategy,
        private bool $psr4FallbackToToken,
        private DiscoveryFileInventory $fileInventory,
        private PhpClassNameParser $classNameParser,
        private Psr4ClassNameResolver $psr4ClassNameResolver,
        private RoutableClassFilter $routableClassFilter
    ) {}

    /**
     * @return list<non-empty-string>
     */
    public function resolve(): array
    {
        $files = $this->sortFilesByPath($this->fileInventory->collect());
        $classSet = [];
        foreach ($files as $fileEntry) {
            $file = $fileEntry[0];
            $fileClasses = $this->resolveFileClasses($file);
            foreach ($this->routableClassFilter->filter($fileClasses) as $className) {
                $classSet[$className] = true;
            }
        }

        /** @var list<non-empty-string> $classes */
        $classes = array_keys($classSet);
        sort($classes);

        return $classes;
    }

    /**
     * @return list<non-empty-string>
     */
    private function resolveFileClasses(string $file): array
    {
        if ('psr4' !== $this->strategy) {
            return $this->classNameParser->parse($file);
        }

        $className = $this->psr4ClassNameResolver->resolve($file);
        if (null !== $className) {
            return [$className];
        }

        if ($this->psr4FallbackToToken) {
            return $this->classNameParser->parse($file);
        }

        return [];
    }

    /**
     * @param list<array{0: non-empty-string, 1: int}> $files
     *
     * @return list<array{0: non-empty-string, 1: int}>
     */
    private function sortFilesByPath(array $files): array
    {
        usort($files, static fn (array $left, array $right): int => $left[0] <=> $right[0]);

        return $files;
    }
}
