<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Command;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheGenerator;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheLoader;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheStorage;
use Sirix\Mezzio\Routing\Attributes\Command\WarmRouteCacheCommand;
use Sirix\Mezzio\Routing\Attributes\CompiledRouteRegistrarCache;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;
use Sirix\Mezzio\Routing\Attributes\Discovery\NullDiscoveredClassesResolver;
use Sirix\Mezzio\Routing\Attributes\DuplicateRouteResolver;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;
use Sirix\Mezzio\Routing\Attributes\RouteCacheWarmer;
use Sirix\Mezzio\Routing\Attributes\RouteDefinition;
use SirixTest\Mezzio\Routing\Attributes\TestMiddleware;
use Symfony\Component\Console\Tester\CommandTester;

use function file_get_contents;
use function is_dir;
use function is_file;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class WarmRouteCacheCommandTest extends TestCase
{
    private ?string $cacheFile = null;

    protected function tearDown(): void
    {
        if (null !== $this->cacheFile && is_file($this->cacheFile)) {
            unlink($this->cacheFile);
        }

        if (null !== $this->cacheFile && is_dir($this->cacheFile)) {
            rmdir($this->cacheFile);
        }
    }

    public function testWarmsConfiguredCacheWithoutRegisteringApplicationRoutes(): void
    {
        $this->cacheFile = sys_get_temp_dir() . '/routing-attributes-warmup-' . uniqid('', true) . '.php';
        $config          = RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes' => [TestMiddleware::class],
                'cache'   => [
                    'enabled' => true,
                    'file'    => $this->cacheFile,
                ],
            ],
        ]);
        $extractor       = $this->createMock(AttributeRouteExtractorInterface::class);
        $extractor
            ->expects(self::once())
            ->method('extract')
            ->with([TestMiddleware::class])
            ->willReturn([
                new RouteDefinition('/warmup', ['GET'], 'handler.service', 'process', [], 'warmup.route'),
            ])
        ;
        $warmer = new RouteCacheWarmer(
            $config,
            $extractor,
            new DuplicateRouteResolver('throw'),
            new NullDiscoveredClassesResolver(),
            new CompiledRouteRegistrarCache(
                $this->cacheFile,
                new RouteCacheGenerator(),
                new RouteCacheStorage(),
                new RouteCacheLoader(),
                $config->cacheFingerprint()
            )
        );

        $tester = new CommandTester(new WarmRouteCacheCommand($warmer, $this->cacheFile));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Route cache warmed', $tester->getDisplay());
        self::assertStringContainsString("'format_version' => 1", (string) file_get_contents($this->cacheFile));
        self::assertStringContainsString($config->cacheFingerprint(), (string) file_get_contents($this->cacheFile));
    }

    public function testFailsWhenCacheIsNotConfigured(): void
    {
        $tester = new CommandTester(new WarmRouteCacheCommand(null, null));

        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString('not configured', $tester->getDisplay());
    }

    public function testReturnsFailureWhenCacheArtifactCannotBeWritten(): void
    {
        $this->cacheFile = sys_get_temp_dir() . '/routing-attributes-warmup-directory-' . uniqid('', true);
        mkdir($this->cacheFile, 0o775, true);
        $config    = RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes' => [],
                'cache'   => [
                    'enabled' => true,
                    'file'    => $this->cacheFile,
                ],
            ],
        ]);
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $extractor
            ->expects(self::once())
            ->method('extract')
            ->with([])
            ->willReturn([])
        ;
        $warmer = new RouteCacheWarmer(
            $config,
            $extractor,
            new DuplicateRouteResolver('throw'),
            new NullDiscoveredClassesResolver(),
            new CompiledRouteRegistrarCache(
                $this->cacheFile,
                new RouteCacheGenerator(),
                new RouteCacheStorage(),
                new RouteCacheLoader(),
                $config->cacheFingerprint()
            )
        );
        $tester = new CommandTester(new WarmRouteCacheCommand($warmer, $this->cacheFile));

        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString('Failed to write route cache file', $tester->getDisplay());
    }

    public function testFailsWithoutWritingArtifactWhenConfiguredDiscoveryPathIsMissing(): void
    {
        $this->cacheFile = sys_get_temp_dir() . '/routing-attributes-warmup-missing-path-' . uniqid('', true) . '.php';
        $config          = RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes'   => [],
                'discovery' => [
                    'enabled' => true,
                    'paths'   => [sys_get_temp_dir() . '/routing-attributes-missing-' . uniqid('', true)],
                ],
                'cache'     => [
                    'enabled' => true,
                    'file'    => $this->cacheFile,
                ],
            ],
        ]);
        $extractor       = $this->createMock(AttributeRouteExtractorInterface::class);
        $extractor->expects(self::never())->method('extract');

        $tester = new CommandTester(new WarmRouteCacheCommand(
            $this->createWarmer($config, $extractor),
            $this->cacheFile
        ));

        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString('does not exist', $tester->getDisplay());
        self::assertFalse(is_file($this->cacheFile));
    }

    public function testFailsWithoutWritingArtifactWhenOneOfSeveralDiscoveryPathsIsMissing(): void
    {
        $this->cacheFile = sys_get_temp_dir() . '/routing-attributes-warmup-multiple-paths-' . uniqid('', true) . '.php';
        $config          = RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes'   => [],
                'discovery' => [
                    'enabled' => true,
                    'paths'   => [
                        sys_get_temp_dir(),
                        sys_get_temp_dir() . '/routing-attributes-missing-' . uniqid('', true),
                    ],
                ],
                'cache'     => [
                    'enabled' => true,
                    'file'    => $this->cacheFile,
                ],
            ],
        ]);
        $extractor       = $this->createMock(AttributeRouteExtractorInterface::class);
        $extractor->expects(self::never())->method('extract');

        $tester = new CommandTester(new WarmRouteCacheCommand(
            $this->createWarmer($config, $extractor),
            $this->cacheFile
        ));

        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString('does not exist', $tester->getDisplay());
        self::assertFalse(is_file($this->cacheFile));
    }

    private function createWarmer(RoutingAttributesConfig $config, AttributeRouteExtractorInterface $extractor): RouteCacheWarmer
    {
        return new RouteCacheWarmer(
            $config,
            $extractor,
            new DuplicateRouteResolver('throw'),
            new NullDiscoveredClassesResolver(),
            new CompiledRouteRegistrarCache(
                (string) $config->cacheFile,
                new RouteCacheGenerator(),
                new RouteCacheStorage(),
                new RouteCacheLoader(),
                $config->cacheFingerprint()
            )
        );
    }
}
