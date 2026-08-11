<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes;

use Mezzio\Router\Route;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheGenerator;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheLoader;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheStorage;
use Sirix\Mezzio\Routing\Attributes\CompiledRouteRegistrarCache;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;
use Sirix\Mezzio\Routing\Attributes\MiddlewarePipelineFactory;
use Sirix\Mezzio\Routing\Attributes\RouteDefinition;
use Sirix\Mezzio\Routing\Attributes\ServiceMiddlewareResolver;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\CacheDefaultWithSetState;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\InMemoryContainer;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\RecordingRouteCollector;
use stdClass;

use function fclose;
use function file_get_contents;
use function file_put_contents;
use function fopen;
use function is_dir;
use function is_file;
use function mkdir;
use function rmdir;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class CompiledRouteRegistrarCacheTest extends TestCase
{
    /** @var list<string> */
    private array $cacheFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->cacheFiles as $cacheFile) {
            if (is_file($cacheFile)) {
                unlink($cacheFile);

                continue;
            }

            if (is_dir($cacheFile)) {
                rmdir($cacheFile);
            }
        }
    }

    public function testSaveAndRegisterRoutesRoundTrip(): void
    {
        $cacheFile          = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        $cache              = $this->createCache($cacheFile);

        $cache->save([
            new RouteDefinition('/compiled', ['GET'], 'handler.service', 'process', ['mw.service'], 'compiled.route'),
        ]);

        $collector       = new RecordingRouteCollector();
        $pipelineFactory = $this->createPipelineFactory([
            'mw.service'      => new TestMiddleware(),
            'handler.service' => new TestMiddleware(),
        ]);

        self::assertTrue($cache->registerRoutes($collector, $pipelineFactory));
        self::assertSame(1, $collector->routeCalls);
        self::assertInstanceOf(Route::class, $collector->lastRoute);
        self::assertSame(
            [
                'sirix_routing_attributes.middleware_display' => 'mw.service -> handler.service::process',
            ],
            $collector->lastRoute->getOptions()
        );
        self::assertStringContainsString('compiled.route', (string) file_get_contents($cacheFile));
    }

    public function testRegisterRoutesReturnsFalseWhenCacheFileMissing(): void
    {
        $cache           = $this->createCache($this->createCacheFilePath());
        $collector       = new RecordingRouteCollector();
        $pipelineFactory = $this->createPipelineFactory([]);

        self::assertFalse($cache->registerRoutes($collector, $pipelineFactory));
        self::assertSame(0, $collector->routeCalls);
    }

    public function testRegisterRoutesIgnoresMetaDifferences(): void
    {
        $cacheFile          = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        $writer             = $this->createCache($cacheFile);
        $reader             = $this->createCache($cacheFile);

        $writer->save([
            new RouteDefinition('/compiled', ['GET'], 'handler.service', 'process', [], 'compiled.route'),
        ]);

        $collector       = new RecordingRouteCollector();
        $pipelineFactory = $this->createPipelineFactory([
            'handler.service' => new TestMiddleware(),
        ]);

        self::assertTrue($reader->registerRoutes($collector, $pipelineFactory));
        self::assertSame(1, $collector->routeCalls);
    }

    public function testCompiledCacheNormalizesBlankRouteName(): void
    {
        $cacheFile          = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        $cache              = $this->createCache($cacheFile);

        $cache->save([
            new RouteDefinition('/compiled-blank-name', ['GET'], 'handler.service', 'process', [], '   '),
        ]);

        $collector = new RecordingRouteCollector();

        self::assertTrue($cache->registerRoutes($collector, $this->createPipelineFactory([
            'handler.service' => new TestMiddleware(),
        ])));
        self::assertNull($collector->lastName);
    }

    public function testCompiledCacheReusesMiddlewareForDuplicateSignatures(): void
    {
        $cacheFile          = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        $cache              = $this->createCache($cacheFile);

        $cache->save([
            new RouteDefinition('/compiled/one', ['GET'], 'handler.service', 'process', ['mw.shared'], 'compiled.one'),
            new RouteDefinition('/compiled/two', ['GET'], 'handler.service', 'process', ['mw.shared'], 'compiled.two'),
        ]);

        $collector = new RecordingRouteCollector();

        self::assertTrue($cache->registerRoutes($collector, $this->createPipelineFactory([
            'mw.shared'       => new TestMiddleware(),
            'handler.service' => new TestMiddleware(),
        ])));
        self::assertCount(2, $collector->middlewareIds);
        self::assertSame($collector->middlewareIds[0], $collector->middlewareIds[1]);
    }

    public function testPreservesExistingRouteOptionsWhenRegisteringFromCompiledCache(): void
    {
        $cacheFile          = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        $cache              = $this->createCache($cacheFile);

        $cache->save([
            new RouteDefinition('/compiled-options', ['GET'], 'handler.service', 'process', [], 'compiled.options.route'),
        ]);

        $collector = new RecordingRouteCollector(static function(Route $route): void {
            $route->setOptions([
                'existing_option' => 'keep-me',
            ]);
        });

        $pipelineFactory = $this->createPipelineFactory([
            'handler.service' => new TestMiddleware(),
        ]);
        self::assertTrue($cache->registerRoutes($collector, $pipelineFactory));
        self::assertInstanceOf(Route::class, $collector->lastRoute);
        self::assertSame('keep-me', $collector->lastRoute->getOptions()['existing_option'] ?? null);
        self::assertSame(
            'handler.service::process',
            $collector->lastRoute->getOptions()['sirix_routing_attributes.middleware_display'] ?? null
        );
    }

    public function testRegisterRoutesWorksForLargeRouteSetWithChunkedArtifact(): void
    {
        $cacheFile          = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        $cache              = $this->createCache($cacheFile);

        $routes = [];
        for ($i = 1; $i <= 1200; ++$i) {
            $routes[] = new RouteDefinition(
                '/bulk/' . $i,
                ['GET'],
                'handler.service',
                'process',
                ['mw.shared'],
                'bulk.route.' . $i
            );
        }

        $cache->save($routes);

        $collector       = new RecordingRouteCollector();
        $pipelineFactory = $this->createPipelineFactory([
            'mw.shared'       => new TestMiddleware(),
            'handler.service' => new TestMiddleware(),
        ]);

        self::assertTrue($cache->registerRoutes($collector, $pipelineFactory));
        self::assertSame(1200, $collector->routeCalls);
        self::assertStringContainsString('$compiledMiddlewares', (string) file_get_contents($cacheFile));
    }

    public function testCompiledCacheUsesInlineArtifactAtInlineLimit(): void
    {
        $cacheFile          = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        $cache              = $this->createCache($cacheFile);

        $routes = [];
        for ($i = 1; $i <= 256; ++$i) {
            $routes[] = new RouteDefinition('/inline/' . $i, ['GET'], 'handler.service', 'process', [], 'inline.route.' . $i);
        }

        $cache->save($routes);

        $content = (string) file_get_contents($cacheFile);
        self::assertStringContainsString('$compiledMiddlewares', $content);
        self::assertStringNotContainsString('$routeChunks', $content);
    }

    public function testCompiledCacheUsesChunkedArtifactAboveInlineLimit(): void
    {
        $cacheFile          = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        $cache              = $this->createCache($cacheFile);

        $routes = [];
        for ($i = 1; $i <= 257; ++$i) {
            $routes[] = new RouteDefinition('/chunked/' . $i, ['GET'], 'handler.service', 'process', [], 'chunked.route.' . $i);
        }

        $cache->save($routes);

        self::assertStringContainsString('$routeChunks', (string) file_get_contents($cacheFile));
    }

    public function testTreatsMalformedPayloadAsCacheMiss(): void
    {
        $cacheFile          = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        file_put_contents(
            $cacheFile,
            <<<'PHP'
                <?php

                declare(strict_types=1);

                return [
                    'meta' => [],
                ];
                PHP
        );

        $cache = $this->createCache($cacheFile);

        $collector       = new RecordingRouteCollector();
        $pipelineFactory = $this->createPipelineFactory([]);

        self::assertFalse($cache->registerRoutes($collector, $pipelineFactory));
    }

    public function testIgnoresCompiledCacheWriteFailure(): void
    {
        $cacheFile          = $this->createDirectoryPath();
        $this->cacheFiles[] = $cacheFile;
        mkdir($cacheFile, 0o775, true);

        $cache = $this->createCache($cacheFile);
        $cache->save([
            new RouteDefinition('/compiled', ['GET'], 'handler.service', 'process', [], 'compiled.route'),
        ]);

        self::assertTrue(is_dir($cacheFile));
    }

    public function testSaveAndRegisterRoutesWithDefaultsRoundTrip(): void
    {
        $cacheFile          = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        $cache              = $this->createCache($cacheFile);

        $cache->save([
            new RouteDefinition('/compiled-defaults', ['GET'], 'handler.service', 'process', [], 'compiled.defaults.route', [
                'foo' => 'bar',
                'num' => 42,
            ]),
        ]);

        $collector       = new RecordingRouteCollector();
        $pipelineFactory = $this->createPipelineFactory([
            'handler.service' => new TestMiddleware(),
        ]);

        self::assertTrue($cache->registerRoutes($collector, $pipelineFactory));
        self::assertSame(1, $collector->routeCalls);
        self::assertInstanceOf(Route::class, $collector->lastRoute);
        self::assertSame('bar', $collector->lastRoute->getOptions()['foo'] ?? null);
        self::assertSame(42, $collector->lastRoute->getOptions()['num'] ?? null);
        self::assertSame(
            'handler.service::process',
            $collector->lastRoute->getOptions()['sirix_routing_attributes.middleware_display'] ?? null
        );
    }

    public function testRejectsNonCacheCompatibleDefaults(): void
    {
        $resource = fopen('php://temp', 'r+');
        self::assertNotFalse($resource);

        try {
            foreach ([
                'closure'          => static fn (): null => null,
                'resource'         => $resource,
                'object'           => new stdClass(),
                'set_state_object' => new CacheDefaultWithSetState('not-supported'),
            ] as $type => $value) {
                try {
                    (new RouteCacheGenerator())->generate([
                        new RouteDefinition('/invalid-default', ['GET'], 'handler.service', 'process', [], 'invalid.default.route', [
                            'value' => $value,
                        ]),
                    ]);
                    self::fail(sprintf('Expected %s default to be rejected.', $type));
                } catch (InvalidConfigurationException $error) {
                    self::assertStringContainsString('cannot be compiled', $error->getMessage());
                }
            }
        } finally {
            fclose($resource);
        }
    }

    public function testRejectsRecursiveArrayDefaultsBeforeCacheGeneration(): void
    {
        $defaults = [
            'nested' => [],
        ];
        $defaults['nested']['parent'] = &$defaults;

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('recursive array reference');

        (new RouteCacheGenerator())->generate([
            new RouteDefinition('/recursive-default', ['GET'], 'handler.service', 'process', [], 'recursive.default.route', [
                'value' => $defaults,
            ]),
        ]);
    }

    public function testAcceptsNestedListDefaults(): void
    {
        $generated = (new RouteCacheGenerator())->generate([
            new RouteDefinition('/list-default', ['GET'], 'handler.service', 'process', [], 'list.default.route', [
                'values' => ['first', 'second'],
            ]),
        ]);

        self::assertStringContainsString('list.default.route', $generated);
    }

    public function testAcceptsSiblingAliasesToTheSameNonRecursiveArray(): void
    {
        $shared = [
            'value' => 'shared',
        ];
        $defaults           = [];
        $defaults['first']  = &$shared;
        $defaults['second'] = &$shared;

        $generated = (new RouteCacheGenerator())->generate([
            new RouteDefinition('/shared-alias', ['GET'], 'handler.service', 'process', [], 'shared.alias.route', [
                'value' => $defaults,
            ]),
        ]);

        self::assertStringContainsString('shared.alias.route', $generated);
    }

    private function createCache(string $cacheFile): CompiledRouteRegistrarCache
    {
        return new CompiledRouteRegistrarCache(
            $cacheFile,
            new RouteCacheGenerator(),
            new RouteCacheStorage(),
            new RouteCacheLoader()
        );
    }

    /**
     * @param array<string, mixed> $services
     */
    private function createPipelineFactory(array $services): MiddlewarePipelineFactory
    {
        return new MiddlewarePipelineFactory(
            new InMemoryContainer($services),
            new ServiceMiddlewareResolver()
        );
    }

    private function createCacheFilePath(): string
    {
        return sys_get_temp_dir() . '/mezzio-routing-attributes-compiled-cache-' . uniqid('', true) . '.php';
    }

    private function createDirectoryPath(): string
    {
        return sys_get_temp_dir() . '/mezzio-routing-attributes-compiled-cache-dir-' . uniqid('', true);
    }
}
