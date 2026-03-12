<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Discovery;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveryClassMapResolver;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\NotMiddleware;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\PingHandler;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\PingRequestHandler;

use function basename;
use function copy;
use function file_exists;
use function glob;
use function is_dir;
use function mkdir;
use function rename;
use function rmdir;
use function sleep;
use function sys_get_temp_dir;
use function touch;
use function uniqid;
use function unlink;

final class DiscoveryClassMapResolverTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/mezzio-routing-attributes-discovery-' . uniqid('', true);
        mkdir($this->tempDir, 0o775, true);

        $fixtures = [
            __DIR__ . '/../Extractor/Fixture/PingHandler.php',
            __DIR__ . '/../Extractor/Fixture/PingRequestHandler.php',
            __DIR__ . '/../Extractor/Fixture/NotMiddleware.php',
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
                unlink($file);
            }
        }

        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    public function testDiscoversOnlyRoutableClassesAndWritesCache(): void
    {
        $cacheFile = $this->tempDir . '/classmap.php';

        /** @var non-empty-string $path */
        $path = $this->tempDir;
        $resolver = new DiscoveryClassMapResolver([$path], $cacheFile, true);

        $result = $resolver->resolve();

        self::assertContains(PingHandler::class, $result['classes']);
        self::assertContains(PingRequestHandler::class, $result['classes']);
        self::assertNotContains(NotMiddleware::class, $result['classes']);
        self::assertNotSame('', $result['fingerprint']);
        self::assertTrue(file_exists($cacheFile));
    }

    public function testSkipsValidationWhenDisabledAndLoadsCachedFingerprint(): void
    {
        $cacheFile = $this->tempDir . '/classmap-no-validate.php';

        /** @var non-empty-string $path */
        $path = $this->tempDir;
        $resolver = new DiscoveryClassMapResolver([$path], $cacheFile, false);
        $first = $resolver->resolve();

        sleep(1);
        touch($this->tempDir . '/PingHandler.php');

        $second = (new DiscoveryClassMapResolver([$path], $cacheFile, false))->resolve();

        self::assertSame($first['fingerprint'], $second['fingerprint']);
        self::assertSame($first['classes'], $second['classes']);
    }

    public function testInvalidatesCacheWhenValidationEnabledAndFileChanges(): void
    {
        $cacheFile = $this->tempDir . '/classmap-validate.php';

        /** @var non-empty-string $path */
        $path = $this->tempDir;
        $first = (new DiscoveryClassMapResolver([$path], $cacheFile, true))->resolve();

        sleep(1);
        touch($this->tempDir . '/PingHandler.php');

        $second = (new DiscoveryClassMapResolver([$path], $cacheFile, true))->resolve();

        self::assertNotSame($first['fingerprint'], $second['fingerprint']);
        self::assertSame($first['classes'], $second['classes']);
    }

    public function testInvalidatesCacheWhenNewFileIsAdded(): void
    {
        $cacheFile = $this->tempDir . '/classmap-add-file.php';

        /** @var non-empty-string $path */
        $path = $this->tempDir;
        $first = (new DiscoveryClassMapResolver([$path], $cacheFile, true))->resolve();

        sleep(1);
        copy(__DIR__ . '/../Extractor/Fixture/PingHandler.php', $this->tempDir . '/PingHandlerCopy.php');

        $second = (new DiscoveryClassMapResolver([$path], $cacheFile, true))->resolve();

        self::assertNotSame($first['fingerprint'], $second['fingerprint']);
    }

    public function testInvalidatesCacheWhenFileIsRemoved(): void
    {
        $cacheFile = $this->tempDir . '/classmap-remove-file.php';

        /** @var non-empty-string $path */
        $path = $this->tempDir;
        $first = (new DiscoveryClassMapResolver([$path], $cacheFile, true))->resolve();

        sleep(1);
        unlink($this->tempDir . '/NotMiddleware.php');

        $second = (new DiscoveryClassMapResolver([$path], $cacheFile, true))->resolve();

        self::assertNotSame($first['fingerprint'], $second['fingerprint']);
    }

    public function testInvalidatesCacheWhenFileIsRenamed(): void
    {
        $cacheFile = $this->tempDir . '/classmap-rename-file.php';

        /** @var non-empty-string $path */
        $path = $this->tempDir;
        $first = (new DiscoveryClassMapResolver([$path], $cacheFile, true))->resolve();

        sleep(1);
        rename($this->tempDir . '/PingRequestHandler.php', $this->tempDir . '/PingRequestHandlerRenamed.php');

        $second = (new DiscoveryClassMapResolver([$path], $cacheFile, true))->resolve();

        self::assertNotSame($first['fingerprint'], $second['fingerprint']);
    }

    public function testResolvesWithPsr4Strategy(): void
    {
        $cacheFile = $this->tempDir . '/classmap-psr4.php';
        $mappingNamespace = 'SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\\';

        /** @var non-empty-string $path */
        $path = $this->tempDir;

        $result = (new DiscoveryClassMapResolver(
            [$path],
            $cacheFile,
            true,
            'ignore',
            'psr4',
            [$path => $mappingNamespace]
        ))->resolve();

        self::assertContains(PingHandler::class, $result['classes']);
        self::assertContains(PingRequestHandler::class, $result['classes']);
        self::assertNotContains(NotMiddleware::class, $result['classes']);
    }

    public function testFallsBackToTokenWhenPsr4CannotResolve(): void
    {
        $cacheFile = $this->tempDir . '/classmap-psr4-fallback.php';

        /** @var non-empty-string $path */
        $path = $this->tempDir;

        $result = (new DiscoveryClassMapResolver(
            [$path],
            $cacheFile,
            true,
            'ignore',
            'psr4',
            [$path => 'Invalid Namespace'],
            true
        ))->resolve();

        self::assertContains(PingHandler::class, $result['classes']);
        self::assertContains(PingRequestHandler::class, $result['classes']);
    }

    public function testSkipsFileWhenPsr4CannotResolveAndFallbackDisabled(): void
    {
        $cacheFile = $this->tempDir . '/classmap-psr4-no-fallback.php';

        /** @var non-empty-string $path */
        $path = $this->tempDir;

        $result = (new DiscoveryClassMapResolver(
            [$path],
            $cacheFile,
            true,
            'ignore',
            'psr4',
            [$path => 'Invalid Namespace'],
            false
        ))->resolve();

        self::assertSame([], $result['classes']);
    }

    public function testInvalidatesCacheWhenDiscoveryOptionsChange(): void
    {
        $cacheFile = $this->tempDir . '/classmap-options.php';

        /** @var non-empty-string $path */
        $path = $this->tempDir;
        $tokenResult = (new DiscoveryClassMapResolver([$path], $cacheFile, true))->resolve();

        $psr4Result = (new DiscoveryClassMapResolver(
            [$path],
            $cacheFile,
            true,
            'ignore',
            'psr4',
            [$path => 'SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\\']
        ))->resolve();

        self::assertNotSame($tokenResult['fingerprint'], $psr4Result['fingerprint']);
    }
}
