<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Cache;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheStorage;

use function chmod;
use function file_put_contents;
use function glob;
use function is_dir;
use function is_file;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class RouteCacheStorageTest extends TestCase
{
    /** @var list<string> */
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            if (is_dir($path)) {
                chmod($path, 0o775);
            }
        }

        foreach ($this->paths as $path) {
            if (is_file($path)) {
                unlink($path);

                continue;
            }

            if (is_dir($path)) {
                rmdir($path);
            }
        }
    }

    public function testSaveIgnoresMkdirFailure(): void
    {
        $baseFile = $this->createPath('mkdir-parent-file');
        $this->paths[] = $baseFile;
        file_put_contents($baseFile, '');

        $cacheFile = $baseFile . '/routes.php';

        (new RouteCacheStorage())->save($cacheFile, '<?php return [];');

        self::assertFalse(is_file($cacheFile));
    }

    public function testSaveIgnoresWriteFailure(): void
    {
        $directory = $this->createPath('write-disabled-dir');
        $this->paths[] = $directory;
        mkdir($directory, 0o775, true);
        chmod($directory, 0o555);

        $cacheFile = $directory . '/routes.php';

        (new RouteCacheStorage())->save($cacheFile, '<?php return [];');

        self::assertFalse(is_file($cacheFile));
    }

    public function testSaveCleansTemporaryFileWhenRenameFails(): void
    {
        $cacheFile = $this->createPath('rename-target-dir');
        $this->paths[] = $cacheFile;
        mkdir($cacheFile, 0o775, true);

        (new RouteCacheStorage())->save($cacheFile, '<?php return [];');

        self::assertSame([], glob($cacheFile . '.tmp.*') ?: []);
    }

    private function createPath(string $prefix): string
    {
        return sys_get_temp_dir() . '/mezzio-routing-attributes-' . $prefix . '-' . uniqid('', true);
    }
}
