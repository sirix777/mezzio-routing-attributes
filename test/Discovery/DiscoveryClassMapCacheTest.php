<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Discovery;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveryClassMapCache;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveryFileInventory;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\PingHandler;

use function basename;
use function copy;
use function file_put_contents;
use function glob;
use function is_dir;
use function is_file;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class DiscoveryClassMapCacheTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/mezzio-routing-attributes-classmap-cache-' . uniqid('', true);
        mkdir($this->tempDir, 0o775, true);

        $fixtures = [
            __DIR__ . '/../Extractor/Fixture/PingHandler.php',
            __DIR__ . '/../Extractor/Fixture/PingRequestHandler.php',
        ];
        foreach ($fixtures as $fixture) {
            copy($fixture, $this->tempDir . '/' . basename($fixture));
        }
    }

    protected function tearDown(): void
    {
        $files = glob($this->tempDir . '/*');
        if (false !== $files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);

                    continue;
                }

                if (is_dir($file)) {
                    rmdir($file);
                }
            }
        }

        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    public function testSaveAndLoadRoundTrip(): void
    {
        /** @var non-empty-string $path */
        $path = $this->tempDir;
        $cacheFile = $this->tempDir . '/classmap-cache.php';
        $inventory = new DiscoveryFileInventory([$path]);
        $files = $inventory->collect();
        $cache = new DiscoveryClassMapCache([$path], $cacheFile, false, $inventory);

        $cache->save([PingHandler::class], $files, 'fingerprint');
        $loaded = $cache->load();

        self::assertNotNull($loaded);
        self::assertSame([PingHandler::class], $loaded['classes']);
        self::assertSame('fingerprint', $loaded['fingerprint']);
    }

    public function testReturnsNullForLegacyPayloadWithoutFormatVersion(): void
    {
        /** @var non-empty-string $path */
        $path = $this->tempDir;
        $cacheFile = $this->tempDir . '/classmap-cache-legacy.php';
        $inventory = new DiscoveryFileInventory([$path]);
        $files = $inventory->collect();

        file_put_contents(
            $cacheFile,
            <<<'PHP'
                <?php

                declare(strict_types=1);

                return [
                    'paths' => ['/tmp/legacy'],
                    'files' => [],
                    'classes' => ['Legacy\\Handler'],
                    'fingerprint' => 'legacy-fingerprint',
                    'inventory_fingerprint' => 'legacy-inventory',
                    'options_signature' => 'default',
                ];
                PHP
        );

        $cache = new DiscoveryClassMapCache([$path], $cacheFile, false, $inventory);

        self::assertNull($cache->load());
        self::assertNotEmpty($files);
    }

    public function testInvalidatesWhenInventoryFingerprintChanges(): void
    {
        /** @var non-empty-string $path */
        $path = $this->tempDir;
        $cacheFile = $this->tempDir . '/classmap-cache-validate.php';
        $inventory = new DiscoveryFileInventory([$path]);
        $files = $inventory->collect();
        $cache = new DiscoveryClassMapCache([$path], $cacheFile, true, $inventory);

        $cache->save([PingHandler::class], $files, 'fingerprint');
        copy(__DIR__ . '/../Extractor/Fixture/NotMiddleware.php', $this->tempDir . '/NotMiddleware.php');

        self::assertNull($cache->load());
    }

    public function testThrowsWhenWriteFailsAndStrategyIsThrow(): void
    {
        /** @var non-empty-string $path */
        $path = $this->tempDir;
        $cacheFile = $this->tempDir . '/cache-as-dir';
        mkdir($cacheFile, 0o775, true);
        $inventory = new DiscoveryFileInventory([$path]);
        $files = $inventory->collect();
        $cache = new DiscoveryClassMapCache(
            [$path],
            $cacheFile,
            true,
            $inventory,
            DiscoveryClassMapCache::WRITE_FAIL_STRATEGY_THROW
        );

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/Reason:/');
        $cache->save([PingHandler::class], $files, 'fingerprint');
    }

    public function testIgnoresWriteFailureByDefault(): void
    {
        /** @var non-empty-string $path */
        $path = $this->tempDir;
        $cacheFile = $this->tempDir . '/cache-as-dir-ignore';
        mkdir($cacheFile, 0o775, true);
        $inventory = new DiscoveryFileInventory([$path]);
        $files = $inventory->collect();
        $cache = new DiscoveryClassMapCache([$path], $cacheFile, true, $inventory);

        $cache->save([PingHandler::class], $files, 'fingerprint');

        self::assertTrue(is_dir($cacheFile));
    }

    public function testInvalidatesWhenOptionsSignatureChanges(): void
    {
        /** @var non-empty-string $path */
        $path = $this->tempDir;
        $cacheFile = $this->tempDir . '/classmap-cache-options.php';
        $inventory = new DiscoveryFileInventory([$path]);
        $files = $inventory->collect();

        $cache = new DiscoveryClassMapCache(
            [$path],
            $cacheFile,
            false,
            $inventory,
            DiscoveryClassMapCache::WRITE_FAIL_STRATEGY_IGNORE,
            'token-signature'
        );
        $cache->save([PingHandler::class], $files, 'fingerprint');

        $cacheWithChangedOptions = new DiscoveryClassMapCache(
            [$path],
            $cacheFile,
            false,
            $inventory,
            DiscoveryClassMapCache::WRITE_FAIL_STRATEGY_IGNORE,
            'psr4-signature'
        );

        self::assertNull($cacheWithChangedOptions->load());
    }
}
