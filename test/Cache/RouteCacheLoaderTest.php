<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Cache;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheLoader;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;

use function file_put_contents;
use function is_file;
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
            if (is_file($cacheFile)) {
                unlink($cacheFile);
            }
        }

        unset($GLOBALS['sirix_route_cache_loader_require_count']);
    }

    public function testValidateAcceptsPayloadWithCallableRegister(): void
    {
        $loader = new RouteCacheLoader();

        self::assertTrue($loader->validate([
            'register' => static function(): void {},
        ]));
    }

    public function testValidateRejectsPayloadWithoutRegister(): void
    {
        $loader = new RouteCacheLoader();

        self::assertFalse($loader->validate([
            'meta' => [],
        ]));
    }

    public function testValidateRejectsPayloadWithNonCallableRegister(): void
    {
        $loader = new RouteCacheLoader();

        self::assertFalse($loader->validate([
            'register' => 'not-a-callable',
        ]));
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

    public function testLoadReturnsNullWhenCacheFileIsMissing(): void
    {
        $loader = new RouteCacheLoader();

        self::assertNull($loader->load($this->createCacheFilePath()));
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
