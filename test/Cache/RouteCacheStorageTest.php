<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Cache;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheStorage;

use function chmod;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function is_dir;
use function is_file;
use function is_link;
use function mkdir;
use function rmdir;
use function str_repeat;
use function symlink;
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
        $baseFile      = $this->createPath('mkdir-parent-file');
        $this->paths[] = $baseFile;
        file_put_contents($baseFile, '');

        $cacheFile = $baseFile . '/routes.php';

        (new RouteCacheStorage())->save($cacheFile, '<?php return [];');

        self::assertFalse(is_file($cacheFile));
    }

    public function testSaveIgnoresWriteFailure(): void
    {
        $directory     = $this->createPath('write-disabled-dir');
        $this->paths[] = $directory;
        mkdir($directory, 0o775, true);
        chmod($directory, 0o555);

        $cacheFile = $directory . '/routes.php';

        (new RouteCacheStorage())->save($cacheFile, '<?php return [];');

        self::assertFalse(is_file($cacheFile));
    }

    public function testSaveCleansTemporaryFileWhenRenameFails(): void
    {
        $directory     = $this->createPath('rename-failure');
        $this->paths[] = $directory;
        mkdir($directory, 0o775, true);
        $cacheFile = $directory . '/' . str_repeat('x', 8192);
        $logger    = new class {
            /** @var list<array{message: string, context: array<string, mixed>}> */
            public array $records = [];

            /** @param array<string, mixed> $context */
            public function error(string $message, array $context): void
            {
                $this->records[] = [
                    'message' => $message,
                    'context' => $context,
                ];
            }
        };

        self::assertFalse((new RouteCacheStorage($logger))->save($cacheFile, '<?php return [];'));

        self::assertSame([], glob($directory . '/.routing-attributes-*') ?: []);
        self::assertSame('replace_artifact', $logger->records[0]['context']['operation']);
    }

    public function testSaveRejectsSymlinkTargetWithoutChangingItsDestination(): void
    {
        $destination   = $this->createPath('symlink-destination');
        $cacheFile     = $this->createPath('symlink-cache');
        $this->paths[] = $destination;
        $this->paths[] = $cacheFile;
        file_put_contents($destination, 'original artifact');
        symlink($destination, $cacheFile);

        self::assertFalse((new RouteCacheStorage())->save($cacheFile, '<?php return [];'));
        self::assertTrue(is_link($cacheFile));
        self::assertSame('original artifact', file_get_contents($destination));
    }

    public function testSaveLogsStructuredFailureForUnsafeTarget(): void
    {
        $cacheFile     = $this->createPath('unsafe-target-directory');
        $this->paths[] = $cacheFile;
        mkdir($cacheFile, 0o775, true);
        $logger = new class {
            /** @var list<array{message: string, context: array<string, mixed>}> */
            public array $records = [];

            /** @param array<string, mixed> $context */
            public function error(string $message, array $context): void
            {
                $this->records[] = [
                    'message' => $message,
                    'context' => $context,
                ];
            }
        };

        self::assertFalse((new RouteCacheStorage($logger))->save($cacheFile, '<?php return [];'));
        self::assertSame('Unable to write compiled route cache artifact.', $logger->records[0]['message']);
        self::assertSame('validate_target', $logger->records[0]['context']['operation']);
        self::assertSame($cacheFile, $logger->records[0]['context']['cache_file']);
    }

    private function createPath(string $prefix): string
    {
        return sys_get_temp_dir() . '/mezzio-routing-attributes-' . $prefix . '-' . uniqid('', true);
    }
}
