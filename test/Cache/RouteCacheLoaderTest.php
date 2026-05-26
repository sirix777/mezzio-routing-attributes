<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Cache;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheLoader;

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
        $cacheFile = $this->createCacheFilePath();
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

        $firstPayload = $loader->load($cacheFile);
        $secondPayload = $loader->load($cacheFile);

        self::assertSame(1, $GLOBALS['sirix_route_cache_loader_require_count']);
        self::assertSame($firstPayload, $secondPayload);
    }

    private function createCacheFilePath(): string
    {
        return sys_get_temp_dir() . '/mezzio-routing-attributes-loader-' . uniqid('', true) . '.php';
    }
}
