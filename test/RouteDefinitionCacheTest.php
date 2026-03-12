<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;
use Sirix\Mezzio\Routing\Attributes\RouteDefinition;
use Sirix\Mezzio\Routing\Attributes\RouteDefinitionCache;

use function file_exists;
use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class RouteDefinitionCacheTest extends TestCase
{
    /** @var list<string> */
    private array $cacheFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->cacheFiles as $cacheFile) {
            if (is_file($cacheFile)) {
                unlink($cacheFile);

                continue;
            }

            if (is_dir($cacheFile)) {
                rmdir($cacheFile);
            }
        }
    }

    public function testSaveAndLoadRoundTrip(): void
    {
        $cacheFile = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        $meta = [
            'format_version' => RouteDefinitionCache::CACHE_FORMAT_VERSION,
            'duplicate_strategy' => 'throw',
            'classes_fingerprint' => 'abc',
        ];
        $cache = new RouteDefinitionCache($cacheFile, $meta);

        $routes = [
            new RouteDefinition('/cached', ['GET'], 'service.cached', 'handle', ['mw.one'], 'cached.route'),
        ];

        $cache->save($routes);
        $loaded = $cache->load();

        self::assertNotNull($loaded);
        self::assertCount(1, $loaded);
        self::assertSame('/cached', $loaded[0]->path);
        self::assertSame(['GET'], $loaded[0]->methods);
        self::assertSame('service.cached', $loaded[0]->handlerService);
        self::assertSame(['mw.one'], $loaded[0]->middlewareServices);
    }

    public function testReturnsNullWhenCacheMetaIsStaleInNonStrictMode(): void
    {
        $cacheFile = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        $writer = new RouteDefinitionCache($cacheFile, [
            'format_version' => RouteDefinitionCache::CACHE_FORMAT_VERSION,
            'duplicate_strategy' => 'throw',
            'classes_fingerprint' => 'old',
        ]);
        $reader = new RouteDefinitionCache($cacheFile, [
            'format_version' => RouteDefinitionCache::CACHE_FORMAT_VERSION,
            'duplicate_strategy' => 'throw',
            'classes_fingerprint' => 'new',
        ]);

        $writer->save([
            new RouteDefinition('/cached', ['GET'], 'service.cached', 'handle', ['mw.one'], 'cached.route'),
        ]);

        self::assertNull($reader->load());
    }

    public function testThrowsWhenCacheMetaIsStaleInStrictMode(): void
    {
        $cacheFile = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        $writer = new RouteDefinitionCache($cacheFile, [
            'format_version' => RouteDefinitionCache::CACHE_FORMAT_VERSION,
            'duplicate_strategy' => 'throw',
            'classes_fingerprint' => 'old',
        ]);
        $reader = new RouteDefinitionCache($cacheFile, [
            'format_version' => RouteDefinitionCache::CACHE_FORMAT_VERSION,
            'duplicate_strategy' => 'throw',
            'classes_fingerprint' => 'new',
        ], true);

        $writer->save([
            new RouteDefinition('/cached', ['GET'], 'service.cached', 'handle', ['mw.one'], 'cached.route'),
        ]);

        $this->expectException(InvalidConfigurationException::class);
        $reader->load();
    }

    public function testReturnsNullWhenCacheDisabled(): void
    {
        $cache = new RouteDefinitionCache();

        self::assertNull($cache->load());
    }

    public function testReturnsNullWhenAnyCachedRouteEntryIsMalformedInNonStrictMode(): void
    {
        $cacheFile = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        file_put_contents(
            $cacheFile,
            <<<'PHP'
                <?php

                declare(strict_types=1);

                return [
                    'meta' => [],
                    'routes' => [
                        [
                            'path' => '/ok',
                            'methods' => ['GET'],
                            'handlerService' => 'service.ok',
                            'handlerMethod' => 'handle',
                            'middlewareServices' => [],
                            'name' => 'ok.route',
                        ],
                        [
                            'path' => '',
                            'methods' => ['GET'],
                            'handlerService' => 'service.broken',
                            'handlerMethod' => 'handle',
                            'middlewareServices' => [],
                            'name' => 'broken.route',
                        ],
                    ],
                ];
                PHP
        );

        $cache = new RouteDefinitionCache($cacheFile, null, false);

        self::assertNull($cache->load());
    }

    public function testThrowsWhenAnyCachedRouteEntryIsMalformedInStrictMode(): void
    {
        $cacheFile = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        file_put_contents(
            $cacheFile,
            <<<'PHP'
                <?php

                declare(strict_types=1);

                return [
                    'meta' => [],
                    'routes' => [
                        [
                            'path' => '/ok',
                            'methods' => ['GET'],
                            'handlerService' => 'service.ok',
                            'handlerMethod' => 'handle',
                            'middlewareServices' => [],
                            'name' => 'ok.route',
                        ],
                        [
                            'path' => '/broken',
                            'methods' => ['GET', ''],
                            'handlerService' => 'service.broken',
                            'handlerMethod' => 'handle',
                            'middlewareServices' => [],
                            'name' => 'broken.route',
                        ],
                    ],
                ];
                PHP
        );

        $cache = new RouteDefinitionCache($cacheFile, null, true);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Route entry "1" is invalid');
        $this->expectExceptionMessage('methods');
        $cache->load();
    }

    public function testThrowsWhenCacheWriteFailsAndStrategyIsThrow(): void
    {
        $cacheFile = $this->createDirectoryPath();
        $this->cacheFiles[] = $cacheFile;
        mkdir($cacheFile, 0o775, true);

        $cache = new RouteDefinitionCache(
            $cacheFile,
            null,
            false,
            RouteDefinitionCache::WRITE_FAIL_STRATEGY_THROW
        );

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/Reason:/');
        $cache->save([
            new RouteDefinition('/cached', ['GET'], 'service.cached', 'handle', ['mw.one'], 'cached.route'),
        ]);
    }

    public function testIgnoresCacheWriteFailureByDefault(): void
    {
        $cacheFile = $this->createDirectoryPath();
        $this->cacheFiles[] = $cacheFile;
        mkdir($cacheFile, 0o775, true);

        $cache = new RouteDefinitionCache($cacheFile);
        $cache->save([
            new RouteDefinition('/cached', ['GET'], 'service.cached', 'handle', ['mw.one'], 'cached.route'),
        ]);

        self::assertTrue(file_exists($cacheFile));
    }

    private function createCacheFilePath(): string
    {
        return sys_get_temp_dir() . '/mezzio-routing-attributes-route-cache-' . uniqid('', true) . '.php';
    }

    private function createDirectoryPath(): string
    {
        return sys_get_temp_dir() . '/mezzio-routing-attributes-route-cache-dir-' . uniqid('', true);
    }
}
