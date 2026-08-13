<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Cache;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheLoader;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;

use function file_put_contents;
use function is_dir;
use function is_file;
use function is_link;
use function mkdir;
use function rmdir;
use function symlink;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class RouteCacheLoaderTest extends TestCase
{
    /** @var list<string> */
    private array $cacheFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->cacheFiles as $cacheFile) {
            if (is_link($cacheFile)) {
                unlink($cacheFile);

                continue;
            }

            if (is_file($cacheFile)) {
                unlink($cacheFile);

                continue;
            }

            if (is_dir($cacheFile)) {
                rmdir($cacheFile);
            }
        }

        unset($GLOBALS['sirix_route_cache_loader_require_count'], $GLOBALS['sirix_route_cache_loader_symlink_executed']);
    }

    public function testLoadReusesPreviouslyLoadedArtifact(): void
    {
        $cacheFile          = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;

        file_put_contents(
            $cacheFile,
            <<<'PHP'
                <?php

                $GLOBALS['sirix_route_cache_loader_require_count'] ??= 0;
                ++$GLOBALS['sirix_route_cache_loader_require_count'];

                return [
                    'format_version' => 2,
                    'config_fingerprint' => '',
                    'register' => static function (): void {},
                ];
                PHP
        );

        $loader = new RouteCacheLoader();

        $firstPayload  = $loader->load($cacheFile);
        $secondPayload = $loader->load($cacheFile);

        self::assertSame(1, $GLOBALS['sirix_route_cache_loader_require_count']);
        self::assertSame($firstPayload, $secondPayload);
    }

    public function testArtifactsAreCachedPerLoaderInstance(): void
    {
        $cacheFile          = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;

        file_put_contents(
            $cacheFile,
            <<<'PHP'
                <?php

                $GLOBALS['sirix_route_cache_loader_require_count'] ??= 0;
                ++$GLOBALS['sirix_route_cache_loader_require_count'];

                return [
                    'format_version' => 2,
                    'config_fingerprint' => '',
                    'register' => static function (): void {},
                ];
                PHP
        );

        (new RouteCacheLoader())->load($cacheFile);
        (new RouteCacheLoader())->load($cacheFile);

        self::assertSame(2, $GLOBALS['sirix_route_cache_loader_require_count']);
    }

    public function testLoadReturnsNullWhenCacheFileIsMissing(): void
    {
        $loader = new RouteCacheLoader();

        self::assertNull($loader->load($this->createCacheFilePath()));
    }

    public function testLoadDoesNotEvaluateSymlinkedArtifact(): void
    {
        $targetFile          = $this->createCacheFilePath();
        $cacheFile           = $this->createCacheFilePath();
        $this->cacheFiles[]  = $targetFile;
        $this->cacheFiles[]  = $cacheFile;
        file_put_contents(
            $targetFile,
            <<<'PHP'
                <?php

                $GLOBALS['sirix_route_cache_loader_symlink_executed'] = true;

                return [
                    'format_version' => 2,
                    'config_fingerprint' => '',
                    'register' => static function (): void {},
                ];
                PHP
        );

        if (! symlink($targetFile, $cacheFile)) {
            self::markTestSkipped('The filesystem does not support symlinks.');
        }

        self::assertTrue(is_link($cacheFile));
        self::assertNull((new RouteCacheLoader())->load($cacheFile));
        self::assertArrayNotHasKey('sirix_route_cache_loader_symlink_executed', $GLOBALS);
    }

    public function testLoadReturnsNullForNonRegularArtifactTarget(): void
    {
        $cacheDirectory     = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheDirectory;
        mkdir($cacheDirectory);

        self::assertNull((new RouteCacheLoader())->load($cacheDirectory));

        rmdir($cacheDirectory);
    }

    public function testLoadReturnsNullForArtifactWithDifferentFormatVersion(): void
    {
        $cacheFile          = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        file_put_contents(
            $cacheFile,
            <<<'PHP'
                <?php

                return [
                    'format_version' => 999,
                    'config_fingerprint' => 'expected',
                    'register' => static function (): void {},
                ];
                PHP
        );

        self::assertNull((new RouteCacheLoader())->load($cacheFile, 'expected'));
    }

    public function testLoadReturnsNullForArtifactWithDifferentConfigurationFingerprint(): void
    {
        $cacheFile          = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        file_put_contents(
            $cacheFile,
            <<<'PHP'
                <?php

                return [
                    'format_version' => 2,
                    'config_fingerprint' => 'old-fingerprint',
                    'register' => static function (): void {},
                ];
                PHP
        );

        self::assertNull((new RouteCacheLoader())->load($cacheFile, 'new-fingerprint'));
    }

    public function testLoadThrowsWhenTopLevelValueIsNotArray(): void
    {
        $cacheFile          = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        file_put_contents(
            $cacheFile,
            <<<'PHP'
                <?php

                return 'not-an-array';
                PHP
        );

        $loader = new RouteCacheLoader();

        $this->expectException(InvalidConfigurationException::class);

        $loader->load($cacheFile);
    }

    public function testLoadThrowsWhenRegisterKeyIsMissing(): void
    {
        $cacheFile          = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        file_put_contents(
            $cacheFile,
            <<<'PHP'
                <?php

                return [
                    'format_version' => 2,
                    'config_fingerprint' => '',
                    'meta' => [],
                ];
                PHP
        );

        $loader = new RouteCacheLoader();

        $this->expectException(InvalidConfigurationException::class);

        $loader->load($cacheFile);
    }

    public function testLoadThrowsWhenRequireFails(): void
    {
        $cacheFile          = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        file_put_contents(
            $cacheFile,
            <<<'PHP'
                <?php

                throw new RuntimeException('Broken artifact.');
                PHP
        );

        $loader = new RouteCacheLoader();

        $this->expectException(InvalidConfigurationException::class);

        $loader->load($cacheFile);
    }

    private function createCacheFilePath(): string
    {
        return sys_get_temp_dir() . '/mezzio-routing-attributes-loader-' . uniqid('', true) . '.php';
    }
}
