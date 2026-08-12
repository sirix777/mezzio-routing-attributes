<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Factory;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sirix\Mezzio\Routing\Attributes\Cache\NullRouteRegistrarCache;
use Sirix\Mezzio\Routing\Attributes\CompiledRouteRegistrarCache;
use Sirix\Mezzio\Routing\Attributes\Factory\CompiledRouteRegistrarCacheFactory;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\InMemoryContainer;

use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;

final class CompiledRouteRegistrarCacheFactoryTest extends TestCase
{
    public function testCreateFromCacheFileReturnsNullCacheWhenDisabled(): void
    {
        $cache = (new CompiledRouteRegistrarCacheFactory())->createFromCacheFile(null);

        self::assertInstanceOf(NullRouteRegistrarCache::class, $cache);
    }

    public function testCreateFromCacheFileReturnsCompiledCacheWhenFileIsConfigured(): void
    {
        $cache = (new CompiledRouteRegistrarCacheFactory())->createFromCacheFile('data/cache/routes.php');

        self::assertInstanceOf(CompiledRouteRegistrarCache::class, $cache);
    }

    public function testInvokeReadsEnabledCacheConfig(): void
    {
        $container = new InMemoryContainer([
            'config' => [
                'routing_attributes' => [
                    'cache' => [
                        'enabled' => true,
                        'file'    => 'data/cache/routes.php',
                    ],
                ],
            ],
        ]);

        $cache = (new CompiledRouteRegistrarCacheFactory())($container);

        self::assertInstanceOf(CompiledRouteRegistrarCache::class, $cache);
    }

    public function testInvokeUsesNullCacheWhenCacheConfigIsAbsent(): void
    {
        $container = new InMemoryContainer([]);

        $cache = (new CompiledRouteRegistrarCacheFactory())($container);

        self::assertInstanceOf(NullRouteRegistrarCache::class, $cache);
    }

    public function testInvokeForwardsWriteFailureToOptionalContainerLogger(): void
    {
        $cacheFile = sys_get_temp_dir() . '/routing-attributes-cache-logger-' . uniqid('', true);
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
        $container = new InMemoryContainer([
            'config'                 => [
                'routing_attributes' => [
                    'cache' => [
                        'enabled' => true,
                        'file'    => $cacheFile,
                    ],
                ],
            ],
            LoggerInterface::class   => $logger,
        ]);

        try {
            $cache = (new CompiledRouteRegistrarCacheFactory())($container);

            self::assertFalse($cache->save([]));
        } finally {
            rmdir($cacheFile);
        }
    }
}
