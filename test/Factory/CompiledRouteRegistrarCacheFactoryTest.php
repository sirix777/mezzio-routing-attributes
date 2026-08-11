<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Factory;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Cache\NullRouteRegistrarCache;
use Sirix\Mezzio\Routing\Attributes\CompiledRouteRegistrarCache;
use Sirix\Mezzio\Routing\Attributes\Factory\CompiledRouteRegistrarCacheFactory;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\InMemoryContainer;

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
}
