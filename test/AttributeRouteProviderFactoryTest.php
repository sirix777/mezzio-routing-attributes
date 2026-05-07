<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\AttributeRouteProvider;
use Sirix\Mezzio\Routing\Attributes\AttributeRouteProviderFactory;
use Sirix\Mezzio\Routing\Attributes\Cache\NullRouteRegistrarCache;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteRegistrarCacheInterface;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveredClassesResolverInterface;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveryClassMapResolver;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveryFileInventory;
use Sirix\Mezzio\Routing\Attributes\Discovery\NullDiscoveredClassesResolver;
use Sirix\Mezzio\Routing\Attributes\Discovery\PhpClassNameParser;
use Sirix\Mezzio\Routing\Attributes\Discovery\Psr4ClassNameResolver;
use Sirix\Mezzio\Routing\Attributes\Discovery\RoutableClassFilter;
use Sirix\Mezzio\Routing\Attributes\DuplicateRouteResolver;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;
use Sirix\Mezzio\Routing\Attributes\MiddlewarePipelineFactory;
use Sirix\Mezzio\Routing\Attributes\ServiceMiddlewareResolver;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\InMemoryContainer;

use function array_merge;
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
        $container = $this->createContainerWithMiddlewarePipeline([
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
        $container = $this->createContainerWithMiddlewarePipeline([
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
        $container = $this->createContainerWithMiddlewarePipeline([
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
        $container = $this->createContainerWithMiddlewarePipeline([
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
        $resolverFromContainer = $this->createDiscoveryResolver();
        $container = $this->createContainerWithMiddlewarePipeline([
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
            DiscoveredClassesResolverInterface::class => $resolverFromContainer,
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
        $container = $this->createContainerWithMiddlewarePipeline([
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

        $provider = (new AttributeRouteProviderFactory())($container);

        self::assertInstanceOf(AttributeRouteProvider::class, $provider);
    }

    public function testRunsDiscoveryResolutionWhenCompiledCacheFileIsMissing(): void
    {
        $cacheFile = sys_get_temp_dir() . '/routing-attributes-discovery-run-' . uniqid('', true) . '.php';
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $resolverFromContainer = $this->createDiscoveryResolver();

        $container = $this->createContainerWithMiddlewarePipeline([
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
            DiscoveredClassesResolverInterface::class => $resolverFromContainer,
        ]);

        $provider = (new AttributeRouteProviderFactory())($container);

        self::assertInstanceOf(AttributeRouteProvider::class, $provider);
    }

    public function testRunsDiscoveryResolutionWhenCompiledCacheFileExistsButIsInvalid(): void
    {
        $cacheFile = sys_get_temp_dir() . '/routing-attributes-discovery-invalid-' . uniqid('', true) . '.php';
        $this->filesToDelete[] = $cacheFile;
        file_put_contents($cacheFile, "<?php\n\ndeclare(strict_types=1);\n\nreturn ['broken' => true];\n");

        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $resolverFromContainer = $this->createDiscoveryResolver();

        $container = $this->createContainerWithMiddlewarePipeline([
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
            DiscoveredClassesResolverInterface::class => $resolverFromContainer,
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

    private function createDiscoveryResolver(): DiscoveryClassMapResolver
    {
        /** @var list<non-empty-string> $paths */
        $paths = [$this->discoveryPath];

        return new DiscoveryClassMapResolver(
            'token',
            true,
            new DiscoveryFileInventory($paths),
            new PhpClassNameParser(),
            new Psr4ClassNameResolver([]),
            new RoutableClassFilter()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultServices(): array
    {
        return [
            RouteRegistrarCacheInterface::class => new NullRouteRegistrarCache(),
            DuplicateRouteResolver::class => new DuplicateRouteResolver('throw'),
            DiscoveredClassesResolverInterface::class => new NullDiscoveredClassesResolver(),
        ];
    }

    /**
     * @param array<string, mixed> $extraServices
     */
    private function createContainerWithMiddlewarePipeline(array $extraServices = []): InMemoryContainer
    {
        $baseServices = array_merge($this->defaultServices(), $extraServices);
        $container = new InMemoryContainer($baseServices);
        $container->set(
            MiddlewarePipelineFactory::class,
            new MiddlewarePipelineFactory($container, new ServiceMiddlewareResolver())
        );

        return $container;
    }
}
