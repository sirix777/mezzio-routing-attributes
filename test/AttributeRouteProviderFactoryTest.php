<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\AttributeRouteProvider;
use Sirix\Mezzio\Routing\Attributes\AttributeRouteProviderFactory;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveryClassMapResolver;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\InMemoryContainer;

use function file_put_contents;
use function is_file;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class AttributeRouteProviderFactoryTest extends TestCase
{
    private string $discoveryPath;

    /** @var list<string> */
    private array $filesToDelete = [];

    protected function setUp(): void
    {
        $this->discoveryPath = __DIR__ . '/Extractor/Fixture';
    }

    protected function tearDown(): void
    {
        foreach ($this->filesToDelete as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
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

    public function testThrowsWhenRemovedLazyServiceResolutionOptionIsUsed(): void
    {
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $container = new InMemoryContainer([
            'config' => [
                'routing_attributes' => [
                    'classes' => [
                        'Foo\Bar\Baz',
                    ],
                    'lazy_service_resolution' => true,
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

    public function testThrowsWhenRemovedCacheBackendOptionUsed(): void
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
                        'file' => '/tmp/mezzio-routing-attributes-serialized.cache',
                        'backend' => 'serialize',
                    ],
                ],
            ],
            AttributeRouteExtractorInterface::class => $extractor,
        ]);

        $this->expectException(InvalidConfigurationException::class);

        (new AttributeRouteProviderFactory())($container);
    }

    public function testThrowsWhenRemovedCacheModeOptionUsed(): void
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
                        'mode' => 'compiled',
                        'file' => '/tmp/mezzio-routing-attributes-compiled.php',
                    ],
                ],
            ],
            AttributeRouteExtractorInterface::class => $extractor,
        ]);

        $this->expectException(InvalidConfigurationException::class);

        (new AttributeRouteProviderFactory())($container);
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

    public function testThrowsWhenRemovedCacheStrictOptionIsUsed(): void
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
                        'strict' => true,
                    ],
                ],
            ],
            AttributeRouteExtractorInterface::class => $extractor,
        ]);

        $this->expectException(InvalidConfigurationException::class);

        (new AttributeRouteProviderFactory())($container);
    }

    public function testThrowsWhenRemovedCacheWriteFailStrategyOptionIsUsed(): void
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
                        'write_fail_strategy' => 'throw',
                    ],
                ],
            ],
            AttributeRouteExtractorInterface::class => $extractor,
        ]);

        $this->expectException(InvalidConfigurationException::class);

        (new AttributeRouteProviderFactory())($container);
    }

    public function testThrowsWhenRemovedCacheBackendOptionUsesInvalidValue(): void
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
                        'backend' => 'invalid',
                    ],
                ],
            ],
            AttributeRouteExtractorInterface::class => $extractor,
        ]);

        $this->expectException(InvalidConfigurationException::class);

        (new AttributeRouteProviderFactory())($container);
    }

    public function testThrowsWhenRemovedCacheModeOptionUsesInvalidValue(): void
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
                        'mode' => 'runtime',
                        'file' => '/tmp/mezzio-routing-attributes.php',
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
                    ],
                ],
            ],
            AttributeRouteExtractorInterface::class => $extractor,
        ]);

        $provider = (new AttributeRouteProviderFactory())($container);

        self::assertInstanceOf(AttributeRouteProvider::class, $provider);
    }

    public function testSkipsDiscoveryResolutionWhenCompiledCacheFileExists(): void
    {
        $cacheFile = sys_get_temp_dir() . '/routing-attributes-discovery-skip-' . uniqid('', true) . '.php';
        $this->filesToDelete[] = $cacheFile;
        file_put_contents(
            $cacheFile,
            <<<'PHP'
                <?php

                return [
                    'register' => static function(): void {},
                ];
                PHP
        );

        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $container = new InMemoryContainer([
            'config' => [
                'routing_attributes' => [
                    'classes' => [],
                    'cache' => [
                        'enabled' => true,
                        'file' => $cacheFile,
                    ],
                    'discovery' => [
                        'enabled' => true,
                        'paths' => [$this->discoveryPath],
                    ],
                ],
            ],
            AttributeRouteExtractorInterface::class => $extractor,
        ]);

        $provider = (new AttributeRouteProviderFactory(
            static function(RoutingAttributesConfig $config): DiscoveryClassMapResolver {
                throw new RuntimeException('Discovery must be skipped on compiled cache hit.');
            }
        ))($container);

        self::assertInstanceOf(AttributeRouteProvider::class, $provider);
    }

    public function testRunsDiscoveryResolutionWhenCompiledCacheFileIsMissing(): void
    {
        $cacheFile = sys_get_temp_dir() . '/routing-attributes-discovery-run-' . uniqid('', true) . '.php';
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $container = new InMemoryContainer([
            'config' => [
                'routing_attributes' => [
                    'classes' => [],
                    'cache' => [
                        'enabled' => true,
                        'file' => $cacheFile,
                    ],
                    'discovery' => [
                        'enabled' => true,
                        'paths' => [$this->discoveryPath],
                    ],
                ],
            ],
            AttributeRouteExtractorInterface::class => $extractor,
        ]);

        $discoveryCalled = false;
        $provider = (new AttributeRouteProviderFactory(
            static function(RoutingAttributesConfig $config) use (&$discoveryCalled): DiscoveryClassMapResolver {
                $discoveryCalled = true;

                return new DiscoveryClassMapResolver($config->discoveryPaths);
            }
        ))($container);

        self::assertInstanceOf(AttributeRouteProvider::class, $provider);
        self::assertTrue($discoveryCalled);
    }

    public function testRunsDiscoveryResolutionWhenCompiledCacheFileExistsButIsInvalid(): void
    {
        $cacheFile = sys_get_temp_dir() . '/routing-attributes-discovery-invalid-' . uniqid('', true) . '.php';
        $this->filesToDelete[] = $cacheFile;
        file_put_contents($cacheFile, "<?php\n\ndeclare(strict_types=1);\n\nreturn ['broken' => true];\n");

        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $container = new InMemoryContainer([
            'config' => [
                'routing_attributes' => [
                    'classes' => [],
                    'cache' => [
                        'enabled' => true,
                        'file' => $cacheFile,
                    ],
                    'discovery' => [
                        'enabled' => true,
                        'paths' => [$this->discoveryPath],
                    ],
                ],
            ],
            AttributeRouteExtractorInterface::class => $extractor,
        ]);

        $discoveryCalled = false;
        $provider = (new AttributeRouteProviderFactory(
            static function(RoutingAttributesConfig $config) use (&$discoveryCalled): DiscoveryClassMapResolver {
                $discoveryCalled = true;

                return new DiscoveryClassMapResolver($config->discoveryPaths);
            }
        ))($container);

        self::assertInstanceOf(AttributeRouteProvider::class, $provider);
        self::assertTrue($discoveryCalled);
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

    public function testThrowsWhenRemovedDiscoveryClassMapCacheOptionUsedWithValidate(): void
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

    public function testThrowsWhenRemovedDiscoveryClassMapCacheOptionUsedWithWriteFailStrategy(): void
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
