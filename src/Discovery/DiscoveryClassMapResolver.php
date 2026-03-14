<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Discovery;

use function array_keys;
use function hash;
use function hash_final;
use function hash_init;
use function hash_update;
use function json_encode;
use function ksort;
use function sort;
use function str_replace;
use function usort;

final readonly class DiscoveryClassMapResolver
{
    /**
     * @param list<non-empty-string>                    $paths
     * @param 'psr4'|'token'                            $strategy
     * @param array<non-empty-string, non-empty-string> $psr4Mappings
     */
    public function __construct(
        private array $paths,
        private ?string $cacheFile = null,
        private bool $validateCache = true,
        private string $writeFailStrategy = DiscoveryClassMapCache::WRITE_FAIL_STRATEGY_IGNORE,
        private string $strategy = 'token',
        private array $psr4Mappings = [],
        private bool $psr4FallbackToToken = true,
        private ?DiscoveryFileInventory $fileInventory = null,
        private ?PhpClassNameParser $classNameParser = null,
        private ?Psr4ClassNameResolver $psr4ClassNameResolver = null,
        private ?RoutableClassFilter $routableClassFilter = null,
        private ?DiscoveryClassMapCache $classMapCache = null
    ) {}

    /** @return array{classes: list<non-empty-string>, fingerprint: non-empty-string} */
    public function resolve(): array
    {
        $cached = $this->classMapCache()->load();
        if (null !== $cached) {
            return $cached;
        }

        $files = $this->sortFilesByPath($this->fileInventory()->collect());
        $classSet = [];
        foreach ($files as $fileEntry) {
            $file = $fileEntry[0];
            $fileClasses = $this->resolveFileClasses($file);
            foreach ($this->routableClassFilter()->filter($fileClasses) as $className) {
                $classSet[$className] = true;
            }
        }

        /** @var list<non-empty-string> $classes */
        $classes = array_keys($classSet);
        sort($classes);

        $fingerprint = $this->createFingerprint($files, $classes);
        $this->classMapCache()->save($classes, $files, $fingerprint);

        return [
            'classes' => $classes,
            'fingerprint' => $fingerprint,
        ];
    }

    /**
     * @return list<non-empty-string>
     */
    private function resolveFileClasses(string $file): array
    {
        if ('psr4' !== $this->strategy) {
            return $this->classNameParser()->parse($file);
        }

        $className = $this->psr4ClassNameResolver()->resolve($file);
        if (null !== $className) {
            return [$className];
        }

        if ($this->psr4FallbackToToken) {
            return $this->classNameParser()->parse($file);
        }

        return [];
    }

    /**
     * @param list<array{0: non-empty-string, 1: int}> $files
     * @param list<non-empty-string>                   $classes
     *
     * @return non-empty-string
     */
    private function createFingerprint(array $files, array $classes): string
    {
        $hashContext = hash_init('sha256');
        foreach ($files as [$file, $mtime]) {
            hash_update($hashContext, $file);
            hash_update($hashContext, '@');
            hash_update($hashContext, (string) $mtime);
            hash_update($hashContext, '|');
        }

        foreach ($classes as $className) {
            hash_update($hashContext, $className);
            hash_update($hashContext, '|');
        }

        hash_update($hashContext, $this->createOptionsSignature());

        return hash_final($hashContext);
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

    private function fileInventory(): DiscoveryFileInventory
    {
        return $this->fileInventory ?? new DiscoveryFileInventory($this->paths);
    }

    private function classNameParser(): PhpClassNameParser
    {
        return $this->classNameParser ?? new PhpClassNameParser();
    }

    private function psr4ClassNameResolver(): Psr4ClassNameResolver
    {
        return $this->psr4ClassNameResolver ?? new Psr4ClassNameResolver($this->psr4Mappings);
    }

    private function routableClassFilter(): RoutableClassFilter
    {
        return $this->routableClassFilter ?? new RoutableClassFilter();
    }

    private function classMapCache(): DiscoveryClassMapCache
    {
        return $this->classMapCache
            ?? new DiscoveryClassMapCache(
                $this->paths,
                $this->cacheFile,
                $this->validateCache,
                $this->fileInventory(),
                $this->writeFailStrategy,
                $this->createOptionsSignature()
            );
    }

    private function createOptionsSignature(): string
    {
        $normalizedMappings = [];
        foreach ($this->psr4Mappings as $path => $namespace) {
            $normalizedPath = str_replace('\\', '/', $path);
            $normalizedMappings[$normalizedPath] = $namespace;
        }
        ksort($normalizedMappings);

        $signature = json_encode(
            [
                'strategy' => $this->strategy,
                'psr4_mappings' => $normalizedMappings,
                'psr4_fallback_to_token' => $this->psr4FallbackToToken,
            ],
            JSON_THROW_ON_ERROR
        );

        return hash('sha256', $signature);
    }
}
