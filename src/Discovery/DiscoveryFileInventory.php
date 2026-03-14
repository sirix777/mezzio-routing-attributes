<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Discovery;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function is_dir;
use function str_ends_with;

final readonly class DiscoveryFileInventory
{
    /**
     * @param list<non-empty-string> $paths
     */
    public function __construct(private array $paths) {}

    /** @return list<array{0: non-empty-string, 1: int}> */
    public function collect(): array
    {
        $files = [];
        foreach ($this->paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

            /** @var SplFileInfo $fileInfo */
            foreach ($iterator as $fileInfo) {
                if (! $fileInfo->isFile()) {
                    continue;
                }

                $file = $fileInfo->getPathname();
                if (! str_ends_with($file, '.php')) {
                    continue;
                }

                $files[] = [$file, $fileInfo->getMTime()];
            }
        }

        return $files;
    }
}
