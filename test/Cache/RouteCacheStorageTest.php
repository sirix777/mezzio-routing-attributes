<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Cache;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
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
        if ('Windows' === PHP_OS_FAMILY) {
            self::markTestSkipped('Windows does not enforce POSIX directory mode bits.');
        }

        $directory     = $this->createPath('write-disabled-dir');
        $this->paths[] = $directory;
        mkdir($directory, 0o775, true);
        chmod($directory, 0o555);

        $cacheFile = $directory . '/routes.php';

        (new RouteCacheStorage())->save($cacheFile, '<?php return [];');

        self::assertFalse(is_file($cacheFile));
    }

    public function testSaveReplacesAnExistingArtifact(): void
    {
        $cacheFile     = $this->createPath('existing-artifact');
        $this->paths[] = $cacheFile;
        file_put_contents($cacheFile, 'old artifact');

        self::assertTrue((new RouteCacheStorage())->save($cacheFile, 'new artifact'));
        self::assertSame('new artifact', file_get_contents($cacheFile));
    }

    public function testSaveCleansTemporaryFileWhenRenameFails(): void
    {
        $directory     = $this->createPath('rename-failure');
        $this->paths[] = $directory;
        mkdir($directory, 0o775, true);
        $cacheFile = $directory . '/' . str_repeat('x', 8192);
        $logger    = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Unable to write compiled route cache artifact.',
                self::callback(static fn (array $context): bool => 'replace_artifact' === $context['operation'])
            )
        ;

        self::assertFalse((new RouteCacheStorage($logger))->save($cacheFile, '<?php return [];'));

        self::assertSame([], glob($directory . '/.routing-attributes-*') ?: []);
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
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Unable to write compiled route cache artifact.',
                self::callback(static fn (array $context): bool => 'validate_target' === $context['operation']
                    && $cacheFile === $context['cache_file'])
            )
        ;

        self::assertFalse((new RouteCacheStorage($logger))->save($cacheFile, '<?php return [];'));
    }

    private function createPath(string $prefix): string
    {
        return sys_get_temp_dir() . '/mezzio-routing-attributes-' . $prefix . '-' . uniqid('', true);
    }
}
