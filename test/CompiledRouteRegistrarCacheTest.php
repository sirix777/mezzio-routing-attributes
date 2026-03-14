<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes;

use Mezzio\Router\Route;
use Mezzio\Router\RouteCollectorInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\MiddlewareInterface;
use Sirix\Mezzio\Routing\Attributes\CompiledRouteRegistrarCache;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;
use Sirix\Mezzio\Routing\Attributes\MiddlewarePipelineFactory;
use Sirix\Mezzio\Routing\Attributes\RouteDefinition;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\InMemoryContainer;

use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;
use function rmdir;
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
        $cacheFile = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        $cache = new CompiledRouteRegistrarCache($cacheFile);

        $cache->save([
            new RouteDefinition('/compiled', ['GET'], 'handler.service', 'process', ['mw.service'], 'compiled.route'),
        ]);

        $collector = new class implements RouteCollectorInterface {
            public int $routeCalls = 0;
            public ?Route $lastRoute = null;

            public function route(string $path, MiddlewareInterface $middleware, ?array $methods = null, ?string $name = null): Route
            {
                ++$this->routeCalls;
                $this->lastRoute = new Route($path, $middleware, $methods, $name);

                return $this->lastRoute;
            }

            public function get(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['GET'], $name);
            }

            public function post(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['POST'], $name);
            }

            public function put(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['PUT'], $name);
            }

            public function patch(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['PATCH'], $name);
            }

            public function delete(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['DELETE'], $name);
            }

            public function any(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, null, $name);
            }

            public function getRoutes(): array
            {
                return [];
            }
        };
        $pipelineFactory = new MiddlewarePipelineFactory(new InMemoryContainer([
            'mw.service' => new TestMiddleware(),
            'handler.service' => new TestMiddleware(),
        ]));

        self::assertTrue($cache->registerRoutes($collector, $pipelineFactory));
        self::assertSame(1, $collector->routeCalls);
        self::assertInstanceOf(Route::class, $collector->lastRoute);
        self::assertSame(
            ['sirix_routing_attributes.middleware_display' => 'mw.service -> handler.service::process'],
            $collector->lastRoute->getOptions()
        );
        self::assertStringContainsString('compiled.route', (string) file_get_contents($cacheFile));
    }

    public function testRegisterRoutesReturnsFalseWhenCacheFileMissing(): void
    {
        $cache = new CompiledRouteRegistrarCache($this->createCacheFilePath());
        $collector = new class implements RouteCollectorInterface {
            public int $routeCalls = 0;

            public function route(string $path, MiddlewareInterface $middleware, ?array $methods = null, ?string $name = null): Route
            {
                ++$this->routeCalls;

                return new Route($path, $middleware, $methods, $name);
            }

            public function get(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['GET'], $name);
            }

            public function post(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['POST'], $name);
            }

            public function put(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['PUT'], $name);
            }

            public function patch(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['PATCH'], $name);
            }

            public function delete(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['DELETE'], $name);
            }

            public function any(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, null, $name);
            }

            public function getRoutes(): array
            {
                return [];
            }
        };
        $pipelineFactory = new MiddlewarePipelineFactory(new InMemoryContainer([]));

        self::assertFalse($cache->registerRoutes($collector, $pipelineFactory));
        self::assertSame(0, $collector->routeCalls);
    }

    public function testRegisterRoutesIgnoresMetaDifferences(): void
    {
        $cacheFile = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        $writer = new CompiledRouteRegistrarCache($cacheFile);
        $reader = new CompiledRouteRegistrarCache($cacheFile);

        $writer->save([
            new RouteDefinition('/compiled', ['GET'], 'handler.service', 'process', [], 'compiled.route'),
        ]);

        $collector = new class implements RouteCollectorInterface {
            public int $routeCalls = 0;

            public function route(string $path, MiddlewareInterface $middleware, ?array $methods = null, ?string $name = null): Route
            {
                ++$this->routeCalls;

                return new Route($path, $middleware, $methods, $name);
            }

            public function get(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['GET'], $name);
            }

            public function post(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['POST'], $name);
            }

            public function put(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['PUT'], $name);
            }

            public function patch(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['PATCH'], $name);
            }

            public function delete(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['DELETE'], $name);
            }

            public function any(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, null, $name);
            }

            public function getRoutes(): array
            {
                return [];
            }
        };
        $pipelineFactory = new MiddlewarePipelineFactory(new InMemoryContainer([
            'handler.service' => new TestMiddleware(),
        ]));

        self::assertTrue($reader->registerRoutes($collector, $pipelineFactory));
        self::assertSame(1, $collector->routeCalls);
    }

    public function testPreservesExistingRouteOptionsWhenRegisteringFromCompiledCache(): void
    {
        $cacheFile = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        $cache = new CompiledRouteRegistrarCache($cacheFile);

        $cache->save([
            new RouteDefinition('/compiled-options', ['GET'], 'handler.service', 'process', [], 'compiled.options.route'),
        ]);

        $collector = new class implements RouteCollectorInterface {
            public ?Route $lastRoute = null;

            public function route(string $path, MiddlewareInterface $middleware, ?array $methods = null, ?string $name = null): Route
            {
                $route = new Route($path, $middleware, $methods, $name);
                $route->setOptions(['existing_option' => 'keep-me']);
                $this->lastRoute = $route;

                return $route;
            }

            public function get(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['GET'], $name);
            }

            public function post(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['POST'], $name);
            }

            public function put(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['PUT'], $name);
            }

            public function patch(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['PATCH'], $name);
            }

            public function delete(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['DELETE'], $name);
            }

            public function any(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, null, $name);
            }

            public function getRoutes(): array
            {
                return [];
            }
        };

        $pipelineFactory = new MiddlewarePipelineFactory(new InMemoryContainer([
            'handler.service' => new TestMiddleware(),
        ]));
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
        $cacheFile = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        $cache = new CompiledRouteRegistrarCache($cacheFile);

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

        $collector = new class implements RouteCollectorInterface {
            public int $routeCalls = 0;

            public function route(string $path, MiddlewareInterface $middleware, ?array $methods = null, ?string $name = null): Route
            {
                ++$this->routeCalls;

                return new Route($path, $middleware, $methods, $name);
            }

            public function get(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['GET'], $name);
            }

            public function post(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['POST'], $name);
            }

            public function put(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['PUT'], $name);
            }

            public function patch(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['PATCH'], $name);
            }

            public function delete(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['DELETE'], $name);
            }

            public function any(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, null, $name);
            }

            public function getRoutes(): array
            {
                return [];
            }
        };
        $pipelineFactory = new MiddlewarePipelineFactory(new InMemoryContainer([
            'mw.shared' => new TestMiddleware(),
            'handler.service' => new TestMiddleware(),
        ]));

        self::assertTrue($cache->registerRoutes($collector, $pipelineFactory));
        self::assertSame(1200, $collector->routeCalls);
        self::assertStringContainsString('$compiledMiddlewares', (string) file_get_contents($cacheFile));
    }

    public function testThrowsWhenPayloadIsMalformed(): void
    {
        $cacheFile = $this->createCacheFilePath();
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

        $cache = new CompiledRouteRegistrarCache($cacheFile);

        $collector = new class implements RouteCollectorInterface {
            public function route(string $path, MiddlewareInterface $middleware, ?array $methods = null, ?string $name = null): Route
            {
                return new Route($path, $middleware, $methods, $name);
            }

            public function get(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['GET'], $name);
            }

            public function post(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['POST'], $name);
            }

            public function put(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['PUT'], $name);
            }

            public function patch(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['PATCH'], $name);
            }

            public function delete(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['DELETE'], $name);
            }

            public function any(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, null, $name);
            }

            public function getRoutes(): array
            {
                return [];
            }
        };
        $pipelineFactory = new MiddlewarePipelineFactory(new InMemoryContainer([]));

        $this->expectException(InvalidConfigurationException::class);
        $cache->registerRoutes($collector, $pipelineFactory);
    }

    public function testIgnoresCompiledCacheWriteFailure(): void
    {
        $cacheFile = $this->createDirectoryPath();
        $this->cacheFiles[] = $cacheFile;
        mkdir($cacheFile, 0o775, true);

        $cache = new CompiledRouteRegistrarCache($cacheFile);
        $cache->save([
            new RouteDefinition('/compiled', ['GET'], 'handler.service', 'process', [], 'compiled.route'),
        ]);

        self::assertTrue(is_dir($cacheFile));
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
