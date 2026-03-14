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
use function glob;
use function is_dir;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
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

    public function testDiscoversOnlyRoutableClasses(): void
    {
        /** @var non-empty-string $path */
        $path = $this->tempDir;
        $resolver = new DiscoveryClassMapResolver([$path]);

        $classes = $resolver->resolve();

        self::assertContains(PingHandler::class, $classes);
        self::assertContains(PingRequestHandler::class, $classes);
        self::assertNotContains(NotMiddleware::class, $classes);
    }

    public function testResolvesWithPsr4Strategy(): void
    {
        $mappingNamespace = 'SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\\';

        /** @var non-empty-string $path */
        $path = $this->tempDir;

        $result = (new DiscoveryClassMapResolver(
            paths: [$path],
            strategy: 'psr4',
            psr4Mappings: [$path => $mappingNamespace]
        ))->resolve();

        self::assertContains(PingHandler::class, $result);
        self::assertContains(PingRequestHandler::class, $result);
        self::assertNotContains(NotMiddleware::class, $result);
    }

    public function testFallsBackToTokenWhenPsr4CannotResolve(): void
    {
        /** @var non-empty-string $path */
        $path = $this->tempDir;

        $result = (new DiscoveryClassMapResolver(
            paths: [$path],
            strategy: 'psr4',
            psr4Mappings: [$path => 'Invalid Namespace'],
            psr4FallbackToToken: true
        ))->resolve();

        self::assertContains(PingHandler::class, $result);
        self::assertContains(PingRequestHandler::class, $result);
    }

    public function testSkipsFileWhenPsr4CannotResolveAndFallbackDisabled(): void
    {
        /** @var non-empty-string $path */
        $path = $this->tempDir;

        $result = (new DiscoveryClassMapResolver(
            paths: [$path],
            strategy: 'psr4',
            psr4Mappings: [$path => 'Invalid Namespace'],
            psr4FallbackToToken: false
        ))->resolve();

        self::assertSame([], $result);
    }
}
