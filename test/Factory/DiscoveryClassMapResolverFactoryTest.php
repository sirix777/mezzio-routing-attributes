<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Factory;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveryClassMapResolver;
use Sirix\Mezzio\Routing\Attributes\Discovery\NullDiscoveredClassesResolver;
use Sirix\Mezzio\Routing\Attributes\Factory\DiscoveryClassMapResolverFactory;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\CallableActionController;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\NotMiddleware;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\InMemoryContainer;

use function basename;
use function copy;
use function glob;
use function is_dir;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class DiscoveryClassMapResolverFactoryTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/mezzio-routing-attributes-factory-discovery-' . uniqid('', true);
        mkdir($this->tempDir, 0o775, true);
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

    public function testInvokeReturnsNullResolverWhenDiscoveryIsDisabled(): void
    {
        $container = new InMemoryContainer([
            'config' => [],
        ]);

        $resolver = (new DiscoveryClassMapResolverFactory())($container);

        self::assertInstanceOf(NullDiscoveredClassesResolver::class, $resolver);
    }

    public function testInvokeReturnsClassMapResolverWhenDiscoveryIsEnabled(): void
    {
        $container = new InMemoryContainer([
            'config' => [
                'routing_attributes' => [
                    'discovery' => [
                        'enabled' => true,
                        'paths'   => [$this->tempDir],
                    ],
                ],
            ],
        ]);

        $resolver = (new DiscoveryClassMapResolverFactory())($container);

        self::assertInstanceOf(DiscoveryClassMapResolver::class, $resolver);
    }

    public function testCallableHandlerModeDiscoversOnlyPlainClassesWithMethodRoutes(): void
    {
        foreach ([
            __DIR__ . '/../Extractor/Fixture/NotMiddleware.php',
            __DIR__ . '/../Extractor/Fixture/CallableActionController.php',
        ] as $fixture) {
            copy($fixture, $this->tempDir . '/' . basename($fixture));
        }
        $container = new InMemoryContainer([
            'config' => [
                'routing_attributes' => [
                    'handlers'  => [
                        'mode' => 'callable',
                    ],
                    'discovery' => [
                        'enabled' => true,
                        'paths'   => [$this->tempDir],
                    ],
                ],
            ],
        ]);

        $classes = (new DiscoveryClassMapResolverFactory())($container)->resolve();

        self::assertContains(CallableActionController::class, $classes);
        self::assertNotContains(NotMiddleware::class, $classes);
    }
}
