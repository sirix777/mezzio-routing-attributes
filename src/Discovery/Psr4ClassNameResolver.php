<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Discovery;

use function array_keys;
use function preg_match;
use function rtrim;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;
use function uksort;

final class Psr4ClassNameResolver
{
    /** @var array<non-empty-string, non-empty-string> */
    private array $normalizedMappings = [];

    /**
     * @param array<non-empty-string, non-empty-string> $mappings
     */
    public function __construct(array $mappings)
    {
        foreach ($mappings as $basePath => $baseNamespace) {
            $normalizedPath = $this->normalizePath($basePath);
            $normalizedNamespace = $this->normalizeNamespace($baseNamespace);
            if ('' === $normalizedPath) {
                continue;
            }

            if ('' === $normalizedNamespace) {
                continue;
            }

            $this->normalizedMappings[$normalizedPath] = $normalizedNamespace;
        }

        uksort(
            $this->normalizedMappings,
            static fn (string $left, string $right): int => strlen($right) <=> strlen($left)
        );
    }

    /** @return null|non-empty-string */
    public function resolve(string $file): ?string
    {
        $normalizedFile = $this->normalizePath($file);
        if ('' === $normalizedFile || ! str_ends_with($normalizedFile, '.php')) {
            return null;
        }

        foreach (array_keys($this->normalizedMappings) as $basePath) {
            if (! str_starts_with($normalizedFile, $basePath . '/')) {
                continue;
            }

            $relative = substr($normalizedFile, strlen($basePath) + 1);
            if ('' === $relative) {
                continue;
            }

            if (! str_ends_with($relative, '.php')) {
                continue;
            }

            $classPart = substr($relative, 0, -4);
            if ('' === $classPart) {
                continue;
            }

            $classPart = str_replace('/', '\\', trim($classPart, '/\\'));
            if ('' === $classPart) {
                continue;
            }

            $candidate = $this->normalizedMappings[$basePath] . '\\' . $classPart;
            if (! $this->isValidClassName($candidate)) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    private function normalizePath(string $path): string
    {
        $normalized = str_replace('\\', '/', trim($path));

        return rtrim($normalized, '/');
    }

    private function normalizeNamespace(string $namespace): string
    {
        return trim($namespace, '\\');
    }

    private function isValidClassName(string $className): bool
    {
        return 1 === preg_match('/^(?:[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)(?:\\\[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)*$/', $className);
    }
}
