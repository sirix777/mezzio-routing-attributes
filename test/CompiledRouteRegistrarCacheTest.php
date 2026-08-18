<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheGenerator;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheLoader;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheStorage;
use Sirix\Mezzio\Routing\Attributes\CompiledRouteRegistrarCache;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractor;
use Sirix\Mezzio\Routing\Attributes\Extractor\ClassEligibilityValidator;
use Sirix\Mezzio\Routing\Attributes\Extractor\MethodSignatureValidator;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteAttributeReader;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteDataNormalizer;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteDefinitionBuilder;
use Sirix\Mezzio\Routing\Attributes\MiddlewarePipelineFactory;
use Sirix\Mezzio\Routing\Attributes\RouteDefinition;
use Sirix\Mezzio\Routing\Attributes\RouteRegistrar;
use Sirix\Mezzio\Routing\Attributes\ServiceMiddlewareResolver;
use Sirix\Mezzio\Routing\Contracts\MiddlewareFactoryInterface;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\SpecificationMiddlewareFactory;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\SpecificationModifierHandler;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\CacheDefaultWithSetState;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\InMemoryContainer;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\RecordingRouteCollector;
use stdClass;

use function array_unique;
use function fclose;
use function file_get_contents;
use function file_put_contents;
use function fopen;
use function is_dir;
use function is_file;
use function mkdir;
use function rmdir;
use function sprintf;
use function substr_count;
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

        $collector = $this->registerRoutes($cache);

        self::assertSame(1, $collector->routeCalls);
        self::assertSame('/compiled', $collector->routes[0]->getPath());
        self::assertSame(['GET'], $collector->routes[0]->getAllowedMethods());
        self::assertSame('compiled.route', $collector->routes[0]->getName());
        self::assertStringContainsString('compiled.route', (string) file_get_contents($cacheFile));
    }

    public function testRegisterRoutesReturnsFalseWhenCacheFileMissing(): void
    {
        $cache = $this->createCache($this->createCacheFilePath());

        self::assertFalse($cache->registerRoutes(new RecordingRouteCollector(), $this->pipelineFactory()));
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

        self::assertSame(1, $this->registerRoutes($reader)->routeCalls);
    }

    public function testTreatsArtifactFromPreviousDeploymentReleaseAsCacheMiss(): void
    {
        $cacheFile          = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        $firstRelease       = RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes' => ['App\Handler\RouteBearingClass'],
                'cache'   => [
                    'enabled' => true,
                    'file'    => $cacheFile,
                    'release' => 'first-release',
                ],
            ],
        ]);
        $secondRelease      = RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes' => ['App\Handler\RouteBearingClass'],
                'cache'   => [
                    'enabled' => true,
                    'file'    => $cacheFile,
                    'release' => 'second-release',
                ],
            ],
        ]);
        $writer             = $this->createCache($cacheFile, $firstRelease->cacheFingerprint());
        $reader             = $this->createCache($cacheFile, $secondRelease->cacheFingerprint());

        self::assertTrue($writer->save([
            new RouteDefinition('/compiled', ['GET'], 'handler.service', 'process', [], 'compiled.route'),
        ]));

        self::assertFalse($reader->registerRoutes(new RecordingRouteCollector(), $this->pipelineFactory()));
    }

    public function testCompiledCacheUsesSharedNameNormalization(): void
    {
        $cacheFile          = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        $cache              = $this->createCache($cacheFile);

        $cache->save([
            new RouteDefinition('/compiled-blank-name', ['GET'], 'handler.service', 'process', [], '   '),
        ]);

        $collector = $this->registerRoutes($cache);

        self::assertNull($collector->lastName);
    }

    public function testCompiledCacheRegistersAllRoutes(): void
    {
        $cacheFile          = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        $cache              = $this->createCache($cacheFile);

        $cache->save([
            new RouteDefinition('/compiled/one', ['GET'], 'handler.service', 'process', ['mw.shared'], 'compiled.one'),
            new RouteDefinition('/compiled/two', ['GET'], 'handler.service', 'process', ['mw.shared'], 'compiled.two'),
        ]);

        self::assertSame(2, $this->registerRoutes($cache)->routeCalls);
    }

    public function testRegistersUsingCollectorAndPipelineFactory(): void
    {
        $cacheFile          = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        $cache              = $this->createCache($cacheFile);
        self::assertTrue($cache->save([
            new RouteDefinition('/legacy', ['GET'], 'handler.service', 'process', [], 'legacy.route'),
        ]));

        $collector = new RecordingRouteCollector();

        self::assertTrue($cache->registerRoutes(
            $collector,
            $this->pipelineFactory()
        ));
        self::assertSame(1, $collector->routeCalls);
    }

    public function testRegisterRoutesWorksForLargeRouteSet(): void
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

        $collector = $this->registerRoutes($cache);
        self::assertSame(1200, $collector->routeCalls);
        self::assertSame('/bulk/1', $collector->routes[0]->getPath());
        self::assertSame('/bulk/1200', $collector->routes[1199]->getPath());
        self::assertCount(1, array_unique($collector->middlewareIds));
        self::assertSinglePreparedRowsArtifact((string) file_get_contents($cacheFile));
    }

    public function testCompiledCacheUsesSinglePreparedRowsArtifact(): void
    {
        $cacheFile          = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        $cache              = $this->createCache($cacheFile);

        $routes = [];
        for ($i = 1; $i <= 17; ++$i) {
            $routes[] = new RouteDefinition('/prepared/' . $i, ['GET'], 'handler.service', 'process', ['mw.shared'], 'prepared.route.' . $i);
        }

        $cache->save($routes);

        $content = (string) file_get_contents($cacheFile);
        $this->assertSinglePreparedRowsArtifact($content);
        self::assertSame(1, substr_count($content, 'createUncachedFromSignature'));
        self::assertSame(1, substr_count($content, "'mw.shared -> handler.service::process'"));
        self::assertSame(17, $this->registerRoutes($cache)->routeCalls);
    }

    public function testSaveAndRegisterRoutesWithMiddlewareSpecificationRoundTrip(): void
    {
        $cacheFile          = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        $cache              = $this->createCache($cacheFile);
        $createdMiddleware  = new class implements MiddlewareInterface {
            public int $processCalls = 0;

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                ++$this->processCalls;

                return $handler->handle($request);
            }
        };
        $factory            = new class($createdMiddleware) implements MiddlewareFactoryInterface {
            public int $createCalls = 0;

            public ?MiddlewareSpecification $receivedSpecification = null;

            public function __construct(private readonly MiddlewareInterface $middleware) {}

            public function create(ContainerInterface $container, MiddlewareSpecification $specification): MiddlewareInterface
            {
                ++$this->createCalls;
                $this->receivedSpecification = $specification;

                return $this->middleware;
            }
        };
        $specification      = new MiddlewareSpecification('middleware.spec', $factory::class, [
            'profile' => 'admin',
        ]);

        self::assertTrue($cache->save([
            new RouteDefinition('/compiled-spec', ['GET'], 'handler.service', 'process', [$specification], 'compiled.spec.route'),
        ]));

        $contents = (string) file_get_contents($cacheFile);
        self::assertStringContainsString(MiddlewareSpecification::class . '::__set_state', $contents);

        $collector = new RecordingRouteCollector();
        self::assertTrue($cache->registerRoutes($collector, $this->pipelineFactory([
            $factory::class => $factory,
        ])));
        $response = $this->createMock(ResponseInterface::class);
        $terminal = new class($response) implements RequestHandlerInterface {
            public function __construct(private readonly ResponseInterface $response) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        self::assertSame($response, $collector->routes[0]->getMiddleware()->process(
            $this->createMock(ServerRequestInterface::class),
            $terminal
        ));
        self::assertSame(1, $factory->createCalls);
        self::assertSame(1, $createdMiddleware->processCalls);
        self::assertNotSame($specification, $factory->receivedSpecification);
        self::assertInstanceOf(MiddlewareSpecification::class, $factory->receivedSpecification);
        self::assertSame($specification->service, $factory->receivedSpecification->service);
        self::assertSame($specification->factory, $factory->receivedSpecification->factory);
        self::assertSame($specification->arguments, $factory->receivedSpecification->arguments);
    }

    public function testDoesNotDeduplicateDelimitedSpecificationAndStringSignaturesInCache(): void
    {
        $cacheFile          = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        $cache              = $this->createCache($cacheFile);

        self::assertTrue($cache->save([
            new RouteDefinition(
                '/compiled-spec-signature',
                ['GET'],
                'handler.service',
                'process',
                [new MiddlewareSpecification('profile', 'factory.id', [])],
                'compiled.spec.signature'
            ),
            new RouteDefinition(
                '/compiled-string-signature',
                ['GET'],
                'handler.service',
                'process',
                ['profile', 'factory.id', 'a:0:{}'],
                'compiled.string.signature'
            ),
        ]));

        self::assertSame(2, substr_count((string) file_get_contents($cacheFile), 'createUncachedFromSignature'));
        self::assertCount(2, array_unique($this->registerRoutes($cache)->middlewareIds));
    }

    public function testSpecificationModifierExtractsAndRegistersThroughColdAndWarmedCachePaths(): void
    {
        SpecificationMiddlewareFactory::$createCalls = 0;
        $routes                                      = $this->extractor()->extract([SpecificationModifierHandler::class]);

        self::assertCount(1, $routes);
        self::assertInstanceOf(MiddlewareSpecification::class, $routes[0]->middlewareServices[0]);

        $coldCollector = new RecordingRouteCollector();
        (new RouteRegistrar())->register($coldCollector, $routes, $this->pipelineFactory([
            SpecificationModifierHandler::class   => $this->routeHandlerService(),
            SpecificationMiddlewareFactory::class => new SpecificationMiddlewareFactory(),
        ]));
        $this->processRouteMiddleware($coldCollector);

        $cacheFile          = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        $cache              = $this->createCache($cacheFile);
        self::assertTrue($cache->save($routes));
        $cachedCollector = new RecordingRouteCollector();
        self::assertTrue($cache->registerRoutes($cachedCollector, $this->pipelineFactory([
            SpecificationModifierHandler::class   => $this->routeHandlerService(),
            SpecificationMiddlewareFactory::class => new SpecificationMiddlewareFactory(),
        ])));
        $this->processRouteMiddleware($cachedCollector);

        self::assertSame(2, SpecificationMiddlewareFactory::$createCalls);
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

        self::assertFalse($cache->registerRoutes(new RecordingRouteCollector(), $this->pipelineFactory()));
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

        $collector = new RecordingRouteCollector();
        self::assertTrue($cache->registerRoutes(
            $collector,
            $this->pipelineFactory()
        ));

        self::assertSame('bar', $collector->routes[0]->getOptions()['foo']);
        self::assertSame(42, $collector->routes[0]->getOptions()['num']);
    }

    public function testCachedRegistrationMatchesColdRegistrationSemantics(): void
    {
        $route = new RouteDefinition('/semantic', null, 'handler.service', 'process', ['mw.service'], '   ', [
            'existing'                                    => 'overridden',
            'nested'                                      => [
                'values' => ['first', 'second'],
            ],
            'sirix_routing_attributes.middleware_display' => 'default-overrides-display',
        ]);
        $configureRoute = static function($registeredRoute): void {
            $registeredRoute->setOptions([
                'existing' => 'preserved',
            ]);
        };
        $coldCollector = new RecordingRouteCollector($configureRoute);
        (new RouteRegistrar())->register($coldCollector, [$route], $this->pipelineFactory());

        $cacheFile          = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        $cache              = $this->createCache($cacheFile);
        self::assertTrue($cache->save([$route]));
        $cachedCollector = new RecordingRouteCollector($configureRoute);
        self::assertTrue($cache->registerRoutes($cachedCollector, $this->pipelineFactory()));

        self::assertSame($coldCollector->lastName, $cachedCollector->lastName);
        self::assertSame($coldCollector->routes[0]->getPath(), $cachedCollector->routes[0]->getPath());
        self::assertSame($coldCollector->routes[0]->getAllowedMethods(), $cachedCollector->routes[0]->getAllowedMethods());
        self::assertSame($coldCollector->routes[0]->getOptions(), $cachedCollector->routes[0]->getOptions());
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

    private function createCache(string $cacheFile, string $configFingerprint = ''): CompiledRouteRegistrarCache
    {
        return new CompiledRouteRegistrarCache(
            $cacheFile,
            new RouteCacheGenerator(),
            new RouteCacheStorage(),
            new RouteCacheLoader(),
            $configFingerprint
        );
    }

    private function registerRoutes(CompiledRouteRegistrarCache $cache): RecordingRouteCollector
    {
        $collector = new RecordingRouteCollector();
        self::assertTrue($cache->registerRoutes($collector, $this->pipelineFactory()));

        return $collector;
    }

    private function extractor(): AttributeRouteExtractor
    {
        return new AttributeRouteExtractor(
            new ClassEligibilityValidator(),
            new RouteAttributeReader(),
            new RouteDefinitionBuilder(
                new RouteAttributeReader(),
                new MethodSignatureValidator(),
                new RouteDataNormalizer()
            )
        );
    }

    private function processRouteMiddleware(RecordingRouteCollector $collector): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $handler  = new class($response) implements RequestHandlerInterface {
            public function __construct(private readonly ResponseInterface $response) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        $collector->routes[0]->getMiddleware()->process(
            $this->createMock(ServerRequestInterface::class),
            $handler
        );
    }

    private function routeHandlerService(): RequestHandlerInterface
    {
        $response = $this->createMock(ResponseInterface::class);

        return new class($response) implements RequestHandlerInterface {
            public function __construct(private readonly ResponseInterface $response) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
    }

    /** @param array<string, mixed> $services */
    private function pipelineFactory(array $services = []): MiddlewarePipelineFactory
    {
        return new MiddlewarePipelineFactory(
            new InMemoryContainer([
                'handler.service' => new TestMiddleware(),
                ...$services,
            ]),
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

    private function assertSinglePreparedRowsArtifact(string $content): void
    {
        self::assertStringContainsString('$compiledMiddlewares', $content);
        self::assertStringContainsString('$middlewareDisplays', $content);
        self::assertStringContainsString('createUncachedFromSignature', $content);
        self::assertStringContainsString('RouteRegistrar::registerPreparedRows($collector, $compiledMiddlewares, $routeRows, $middlewareDisplays)', $content);
        self::assertStringNotContainsString('$collector->route(', $content);
        self::assertStringNotContainsString('$routeChunks', $content);
        self::assertStringNotContainsString('$serviceTable', $content);
        self::assertStringNotContainsString('$methodTable', $content);
        self::assertStringNotContainsString('$middlewareTable', $content);
        self::assertStringNotContainsString('$compiledSignatureTable', $content);
    }
}
