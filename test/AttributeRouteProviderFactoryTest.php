<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\AttributeRouteProvider;
use Sirix\Mezzio\Routing\Attributes\AttributeRouteProviderFactory;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\InMemoryContainer;

final class AttributeRouteProviderFactoryTest extends TestCase
{
    private string $discoveryPath;

    protected function setUp(): void
    {
        $this->discoveryPath = __DIR__ . '/Extractor/Fixture';
    }

    public function testCreatesProviderFromValidConfiguration(): void
    {
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $container = new InMemoryContainer([
            'config' => [
                'routing_attributes' => [
                    'classes' => [
                        'Foo\Bar\Baz',
                    ],
                ],
            ],
            AttributeRouteExtractorInterface::class => $extractor,
        ]);

        $provider = (new AttributeRouteProviderFactory())($container);

        self::assertInstanceOf(AttributeRouteProvider::class, $provider);
    }

    public function testCreatesProviderWhenConfigMissing(): void
    {
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $container = new InMemoryContainer([
            AttributeRouteExtractorInterface::class => $extractor,
        ]);

        $provider = (new AttributeRouteProviderFactory())($container);

        self::assertInstanceOf(AttributeRouteProvider::class, $provider);
    }

    public function testThrowsForInvalidClassesConfig(): void
    {
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $container = new InMemoryContainer([
            'config' => [
                'routing_attributes' => [
                    'classes' => 'not-an-array',
                ],
            ],
            AttributeRouteExtractorInterface::class => $extractor,
        ]);

        $this->expectException(InvalidConfigurationException::class);

        (new AttributeRouteProviderFactory())($container);
    }

    public function testAllowsDuplicateStrategyIgnore(): void
    {
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $container = new InMemoryContainer([
            'config' => [
                'routing_attributes' => [
                    'classes' => [
                        'Foo\Bar\Baz',
                    ],
                    'duplicate_strategy' => 'ignore',
                ],
            ],
            AttributeRouteExtractorInterface::class => $extractor,
        ]);

        $provider = (new AttributeRouteProviderFactory())($container);

        self::assertInstanceOf(AttributeRouteProvider::class, $provider);
    }

    public function testThrowsForInvalidDuplicateStrategy(): void
    {
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $container = new InMemoryContainer([
            'config' => [
                'routing_attributes' => [
                    'classes' => [
                        'Foo\Bar\Baz',
                    ],
                    'duplicate_strategy' => 'invalid',
                ],
            ],
            AttributeRouteExtractorInterface::class => $extractor,
        ]);

        $this->expectException(InvalidConfigurationException::class);

        (new AttributeRouteProviderFactory())($container);
    }

    public function testAllowsCacheConfigurationWhenEnabled(): void
    {
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $container = new InMemoryContainer([
            'config' => [
                'routing_attributes' => [
                    'classes' => [
                        'Foo\Bar\Baz',
                    ],
                    'cache' => [
                        'enabled' => true,
                        'file' => '/tmp/mezzio-routing-attributes.php',
                    ],
                ],
            ],
            AttributeRouteExtractorInterface::class => $extractor,
        ]);

        $provider = (new AttributeRouteProviderFactory())($container);

        self::assertInstanceOf(AttributeRouteProvider::class, $provider);
    }

    public function testThrowsForInvalidCacheType(): void
    {
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $container = new InMemoryContainer([
            'config' => [
                'routing_attributes' => [
                    'classes' => [
                        'Foo\Bar\Baz',
                    ],
                    'cache' => 'invalid',
                ],
            ],
            AttributeRouteExtractorInterface::class => $extractor,
        ]);

        $this->expectException(InvalidConfigurationException::class);

        (new AttributeRouteProviderFactory())($container);
    }

    public function testThrowsForInvalidCacheEnabledFlag(): void
    {
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $container = new InMemoryContainer([
            'config' => [
                'routing_attributes' => [
                    'classes' => [
                        'Foo\Bar\Baz',
                    ],
                    'cache' => [
                        'enabled' => 'yes',
                        'file' => '/tmp/mezzio-routing-attributes.php',
                    ],
                ],
            ],
            AttributeRouteExtractorInterface::class => $extractor,
        ]);

        $this->expectException(InvalidConfigurationException::class);

        (new AttributeRouteProviderFactory())($container);
    }

    public function testThrowsWhenCacheEnabledWithoutValidFile(): void
    {
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $container = new InMemoryContainer([
            'config' => [
                'routing_attributes' => [
                    'classes' => [
                        'Foo\Bar\Baz',
                    ],
                    'cache' => [
                        'enabled' => true,
                    ],
                ],
            ],
            AttributeRouteExtractorInterface::class => $extractor,
        ]);

        $this->expectException(InvalidConfigurationException::class);

        (new AttributeRouteProviderFactory())($container);
    }

    public function testThrowsForInvalidCacheStrictFlag(): void
    {
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $container = new InMemoryContainer([
            'config' => [
                'routing_attributes' => [
                    'classes' => [
                        'Foo\Bar\Baz',
                    ],
                    'cache' => [
                        'enabled' => true,
                        'file' => '/tmp/mezzio-routing-attributes.php',
                        'strict' => 'yes',
                    ],
                ],
            ],
            AttributeRouteExtractorInterface::class => $extractor,
        ]);

        $this->expectException(InvalidConfigurationException::class);

        (new AttributeRouteProviderFactory())($container);
    }

    public function testThrowsForInvalidCacheWriteFailStrategy(): void
    {
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $container = new InMemoryContainer([
            'config' => [
                'routing_attributes' => [
                    'classes' => [
                        'Foo\Bar\Baz',
                    ],
                    'cache' => [
                        'enabled' => true,
                        'file' => '/tmp/mezzio-routing-attributes.php',
                        'write_fail_strategy' => 'invalid',
                    ],
                ],
            ],
            AttributeRouteExtractorInterface::class => $extractor,
        ]);

        $this->expectException(InvalidConfigurationException::class);

        (new AttributeRouteProviderFactory())($container);
    }

    public function testAllowsDiscoveryConfigurationWhenEnabled(): void
    {
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $container = new InMemoryContainer([
            'config' => [
                'routing_attributes' => [
                    'classes' => [],
                    'discovery' => [
                        'enabled' => true,
                        'paths' => [$this->discoveryPath],
                        'class_map_cache' => [
                            'enabled' => false,
                            'validate' => true,
                        ],
                    ],
                ],
            ],
            AttributeRouteExtractorInterface::class => $extractor,
        ]);

        $provider = (new AttributeRouteProviderFactory())($container);

        self::assertInstanceOf(AttributeRouteProvider::class, $provider);
    }

    public function testThrowsForInvalidDiscoveryType(): void
    {
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $container = new InMemoryContainer([
            'config' => [
                'routing_attributes' => [
                    'classes' => [],
                    'discovery' => 'invalid',
                ],
            ],
            AttributeRouteExtractorInterface::class => $extractor,
        ]);

        $this->expectException(InvalidConfigurationException::class);

        (new AttributeRouteProviderFactory())($container);
    }

    public function testThrowsForInvalidDiscoveryPathEntry(): void
    {
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $container = new InMemoryContainer([
            'config' => [
                'routing_attributes' => [
                    'classes' => [],
                    'discovery' => [
                        'enabled' => true,
                        'paths' => [''],
                    ],
                ],
            ],
            AttributeRouteExtractorInterface::class => $extractor,
        ]);

        $this->expectException(InvalidConfigurationException::class);

        (new AttributeRouteProviderFactory())($container);
    }

    public function testThrowsForInvalidDiscoveryClassMapCacheValidate(): void
    {
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $container = new InMemoryContainer([
            'config' => [
                'routing_attributes' => [
                    'classes' => [],
                    'discovery' => [
                        'enabled' => true,
                        'paths' => [$this->discoveryPath],
                        'class_map_cache' => [
                            'enabled' => true,
                            'file' => '/tmp/discovery-cache.php',
                            'validate' => 'yes',
                        ],
                    ],
                ],
            ],
            AttributeRouteExtractorInterface::class => $extractor,
        ]);

        $this->expectException(InvalidConfigurationException::class);

        (new AttributeRouteProviderFactory())($container);
    }

    public function testThrowsForInvalidDiscoveryClassMapCacheWriteFailStrategy(): void
    {
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $container = new InMemoryContainer([
            'config' => [
                'routing_attributes' => [
                    'classes' => [],
                    'discovery' => [
                        'enabled' => true,
                        'paths' => [$this->discoveryPath],
                        'class_map_cache' => [
                            'enabled' => true,
                            'file' => '/tmp/discovery-cache.php',
                            'validate' => true,
                            'write_fail_strategy' => 'invalid',
                        ],
                    ],
                ],
            ],
            AttributeRouteExtractorInterface::class => $extractor,
        ]);

        $this->expectException(InvalidConfigurationException::class);

        (new AttributeRouteProviderFactory())($container);
    }
}
