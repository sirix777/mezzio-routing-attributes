<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Command;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheGenerator;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheLoader;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheStorage;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteRegistrarCacheInterface;
use Sirix\Mezzio\Routing\Attributes\Command\WarmRouteCacheCommand;
use Sirix\Mezzio\Routing\Attributes\Command\WarmRouteCacheCommandFactory;
use Sirix\Mezzio\Routing\Attributes\CompiledRouteRegistrarCache;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveredClassesResolverInterface;
use Sirix\Mezzio\Routing\Attributes\Discovery\NullDiscoveredClassesResolver;
use Sirix\Mezzio\Routing\Attributes\DuplicateRouteResolver;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;
use Sirix\Mezzio\Routing\Attributes\RouteDefinition;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\InMemoryContainer;
use SirixTest\Mezzio\Routing\Attributes\TestMiddleware;
use Symfony\Component\Console\Tester\CommandTester;

use function file_get_contents;
use function is_file;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class WarmRouteCacheCommandFactoryTest extends TestCase
{
    private ?string $cacheFile = null;

    protected function tearDown(): void
    {
        if (null !== $this->cacheFile && is_file($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }

    public function testBuildsWiredCommandThatWritesConfiguredCache(): void
    {
        $this->cacheFile = sys_get_temp_dir() . '/routing-attributes-warmup-factory-' . uniqid('', true) . '.php';
        $rootConfig      = [
            'routing_attributes' => [
                'classes' => [TestMiddleware::class],
                'cache'   => [
                    'enabled' => true,
                    'file'    => $this->cacheFile,
                    'release' => 'test-release',
                ],
            ],
        ];
        $config          = RoutingAttributesConfig::fromRootConfig($rootConfig);
        $extractor       = $this->createMock(AttributeRouteExtractorInterface::class);
        $extractor
            ->expects(self::once())
            ->method('extract')
            ->with([TestMiddleware::class])
            ->willReturn([
                new RouteDefinition('/factory-warmup', ['GET'], 'handler.service', 'process', [], 'factory.warmup'),
            ])
        ;
        $container = new InMemoryContainer([
            'config'                                  => $rootConfig,
            AttributeRouteExtractorInterface::class   => $extractor,
            DuplicateRouteResolver::class             => new DuplicateRouteResolver('throw'),
            DiscoveredClassesResolverInterface::class => new NullDiscoveredClassesResolver(),
            RouteRegistrarCacheInterface::class       => new CompiledRouteRegistrarCache(
                $this->cacheFile,
                new RouteCacheGenerator(),
                new RouteCacheStorage(),
                new RouteCacheLoader(),
                $config->cacheFingerprint()
            ),
        ]);

        $command = (new WarmRouteCacheCommandFactory())($container);

        self::assertInstanceOf(WarmRouteCacheCommand::class, $command);
        self::assertSame(0, (new CommandTester($command))->execute([]));
        self::assertStringContainsString('factory.warmup', (string) file_get_contents($this->cacheFile));
    }
}
