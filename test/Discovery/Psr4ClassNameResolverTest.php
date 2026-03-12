<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Discovery;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Discovery\Psr4ClassNameResolver;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\PingHandler;

use function str_replace;

final class Psr4ClassNameResolverTest extends TestCase
{
    public function testResolvesClassFromMappedPath(): void
    {
        $resolver = new Psr4ClassNameResolver([
            __DIR__ . '/../Extractor/Fixture' => 'SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\\',
        ]);

        $resolved = $resolver->resolve(__DIR__ . '/../Extractor/Fixture/PingHandler.php');

        self::assertSame(PingHandler::class, $resolved);
    }

    public function testReturnsNullForFileOutsideMappings(): void
    {
        $resolver = new Psr4ClassNameResolver([
            __DIR__ . '/../Extractor/Fixture' => 'SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\\',
        ]);

        $resolved = $resolver->resolve(__DIR__ . '/DiscoveryClassMapResolverTest.php');

        self::assertNull($resolved);
    }

    public function testHandlesBackslashPathMapping(): void
    {
        $resolver = new Psr4ClassNameResolver([
            str_replace('/', '\\', __DIR__ . '/../Extractor/Fixture') => 'SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture',
        ]);

        $resolved = $resolver->resolve(__DIR__ . '/../Extractor/Fixture/PingHandler.php');

        self::assertSame(PingHandler::class, $resolved);
    }
}
