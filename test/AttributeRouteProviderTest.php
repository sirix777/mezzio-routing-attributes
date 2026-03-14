<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes;

use Mezzio\Router\Route;
use Mezzio\Router\RouteCollectorInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\AttributeRouteProvider;
use Sirix\Mezzio\Routing\Attributes\CompiledRouteRegistrarCache;
use Sirix\Mezzio\Routing\Attributes\Exception\DuplicateRouteDefinitionException;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidServiceDefinitionException;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;
use Sirix\Mezzio\Routing\Attributes\MiddlewarePipelineFactory;
use Sirix\Mezzio\Routing\Attributes\RouteDefinition;

use function file_exists;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class AttributeRouteProviderTest extends TestCase
{
    /** @var list<string> */
    private array $cacheFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->cacheFiles as $cacheFile) {
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }
        }
    }

    public function testRegistersExtractedRoutesInCollector(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $collector = $this->createMock(RouteCollectorInterface::class);

        $extractor
            ->expects(self::once())
            ->method('extract')
            ->with([TestMiddleware::class])
            ->willReturn([
                new RouteDefinition('/test', ['GET'], TestMiddleware::class, 'process', [], 'test.route'),
            ])
        ;

        $container
            ->expects(self::never())
            ->method('get')
        ;

        $collector
            ->expects(self::once())
            ->method('route')
            ->with(
                '/test',
                self::callback(static fn (mixed $registered): bool => $registered instanceof MiddlewareInterface),
                ['GET'],
                'test.route'
            )
            ->willReturnCallback(static fn (
                string $path,
                MiddlewareInterface $registered,
                ?array $methods,
                ?string $name
            ): Route => new Route('/test', $registered, ['GET'], 'test.route'))
        ;

        $provider = new AttributeRouteProvider($container, $extractor, [TestMiddleware::class]);
        $provider->registerRoutes($collector);
    }

    public function testWrapsRequestHandlerServiceAsMiddleware(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $collector = $this->createMock(RouteCollectorInterface::class);

        $extractor
            ->expects(self::once())
            ->method('extract')
            ->with([TestRequestHandler::class])
            ->willReturn([
                new RouteDefinition('/handler', ['GET'], TestRequestHandler::class, 'handle', [], 'handler.route'),
            ])
        ;

        $container
            ->expects(self::never())
            ->method('get')
        ;

        $collector
            ->expects(self::once())
            ->method('route')
            ->with(
                '/handler',
                self::callback(static fn (mixed $middleware): bool => $middleware instanceof MiddlewareInterface),
                ['GET'],
                'handler.route'
            )
            ->willReturnCallback(
                static fn (string $path, MiddlewareInterface $middleware, ?array $methods, ?string $name): Route => new Route(
                    '/handler',
                    $middleware,
                    ['GET'],
                    'handler.route'
                )
            )
        ;

        $provider = new AttributeRouteProvider($container, $extractor, [TestRequestHandler::class]);
        $provider->registerRoutes($collector);
    }

    public function testPreservesExistingRouteOptionsWhenSettingMiddlewareDisplay(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $collector = $this->createMock(RouteCollectorInterface::class);
        $registeredRoute = null;

        $extractor
            ->expects(self::once())
            ->method('extract')
            ->with([TestMiddleware::class])
            ->willReturn([
                new RouteDefinition('/options', ['GET'], TestMiddleware::class, 'process', [], 'options.route'),
            ])
        ;

        $collector
            ->expects(self::once())
            ->method('route')
            ->willReturnCallback(static function(
                string $path,
                MiddlewareInterface $middleware,
                ?array $methods,
                ?string $name
            ) use (&$registeredRoute): Route {
                $registeredRoute = new Route('/options', $middleware, ['GET'], 'options.route');
                $registeredRoute->setOptions(['existing_option' => 'keep-me']);

                return $registeredRoute;
            })
        ;

        $provider = new AttributeRouteProvider($container, $extractor, [TestMiddleware::class]);
        $provider->registerRoutes($collector);

        self::assertInstanceOf(Route::class, $registeredRoute);
        self::assertSame('keep-me', $registeredRoute->getOptions()['existing_option'] ?? null);
        self::assertSame(
            TestMiddleware::class . '::process',
            $registeredRoute->getOptions()['sirix_routing_attributes.middleware_display'] ?? null
        );
    }

    public function testBuildsPipelineForMultipleRouteMiddlewares(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $collector = $this->createMock(RouteCollectorInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $finalHandler = new class($response) implements RequestHandlerInterface {
            public function __construct(private readonly ResponseInterface $response) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
        $request = $this->createMock(ServerRequestInterface::class);
        $first = new class implements MiddlewareInterface {
            public bool $called = false;

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->called = true;

                return $handler->handle($request);
            }
        };
        $second = new class($response) implements MiddlewareInterface {
            public bool $called = false;

            public function __construct(private readonly ResponseInterface $response) {}

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->called = true;

                return $this->response;
            }
        };

        $extractor
            ->expects(self::once())
            ->method('extract')
            ->with([TestRequestHandler::class])
            ->willReturn([
                new RouteDefinition('/stack', ['GET'], 'handler.service', 'handle', ['first.service', 'second.service'], 'stack.route'),
            ])
        ;

        $container
            ->expects(self::exactly(2))
            ->method('get')
            ->willReturnMap([
                ['first.service', $first],
                ['second.service', $second],
            ])
        ;

        $collector
            ->expects(self::once())
            ->method('route')
            ->with(
                '/stack',
                self::callback(static fn (mixed $middleware): bool => $middleware instanceof MiddlewareInterface),
                ['GET'],
                'stack.route'
            )
            ->willReturnCallback(
                static function(string $path, MiddlewareInterface $middleware, ?array $methods, ?string $name) use (
                    $request,
                    $finalHandler,
                    $response
                ): Route {
                    self::assertSame($response, $middleware->process($request, $finalHandler));

                    return new Route('/stack', $middleware, ['GET'], 'stack.route');
                }
            )
        ;

        $provider = new AttributeRouteProvider($container, $extractor, [TestRequestHandler::class]);
        $provider->registerRoutes($collector);

        self::assertTrue($first->called);
        self::assertTrue($second->called);
    }

    public function testLazyServiceResolutionDefersContainerLookupUntilRouteExecution(): void
    {
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $collector = $this->createMock(RouteCollectorInterface::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $fallbackHandler = new class($response) implements RequestHandlerInterface {
            public function __construct(private readonly ResponseInterface $response) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
        $service = new class($response) implements MiddlewareInterface {
            public function __construct(private readonly ResponseInterface $response) {}

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $this->response;
            }
        };
        $container = new class($service) implements ContainerInterface {
            public int $getCalls = 0;

            public function __construct(private readonly MiddlewareInterface $service) {}

            public function get(string $id): mixed
            {
                ++$this->getCalls;

                if ('lazy.service' !== $id) {
                    throw new RuntimeException('Unexpected service id: ' . $id);
                }

                return $this->service;
            }

            public function has(string $id): bool
            {
                return 'lazy.service' === $id;
            }
        };

        $extractor
            ->expects(self::once())
            ->method('extract')
            ->with([TestRequestHandler::class])
            ->willReturn([
                new RouteDefinition('/lazy', ['GET'], 'lazy.service', 'process', [], 'lazy.route'),
            ])
        ;

        $collector
            ->expects(self::once())
            ->method('route')
            ->willReturnCallback(static function(
                string $path,
                MiddlewareInterface $middleware,
                ?array $methods,
                ?string $name
            ) use ($container, $request, $fallbackHandler, $response): Route {
                self::assertSame(0, $container->getCalls);
                self::assertSame($response, $middleware->process($request, $fallbackHandler));
                self::assertSame(1, $container->getCalls);

                return new Route('/lazy', $middleware, ['GET'], 'lazy.route');
            })
        ;

        $provider = new AttributeRouteProvider(
            $container,
            $extractor,
            [TestRequestHandler::class],
            AttributeRouteProvider::DUPLICATE_STRATEGY_THROW,
            null,
            new MiddlewarePipelineFactory($container)
        );
        $provider->registerRoutes($collector);

        self::assertSame(1, $container->getCalls);
    }

    public function testThrowsWhenDuplicateRouteNamesDetected(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $collector = $this->createMock(RouteCollectorInterface::class);

        $extractor
            ->expects(self::once())
            ->method('extract')
            ->with([TestRequestHandler::class])
            ->willReturn([
                new RouteDefinition('/a', ['GET'], TestRequestHandler::class, 'handle', [], 'dup.name'),
                new RouteDefinition('/b', ['GET'], TestRequestHandler::class, 'handle', [], 'dup.name'),
            ])
        ;

        $collector
            ->expects(self::never())
            ->method('route')
        ;

        $this->expectException(DuplicateRouteDefinitionException::class);

        $provider = new AttributeRouteProvider(
            $container,
            $extractor,
            [TestRequestHandler::class],
            AttributeRouteProvider::DUPLICATE_STRATEGY_THROW
        );
        $provider->registerRoutes($collector);
    }

    public function testIgnoresDuplicatesWhenConfigured(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $collector = $this->createMock(RouteCollectorInterface::class);

        $extractor
            ->expects(self::once())
            ->method('extract')
            ->with([TestRequestHandler::class])
            ->willReturn([
                new RouteDefinition('/same', ['GET'], 'first.service', 'process', [], 'same.route'),
                new RouteDefinition('/same', ['GET'], 'second.service', 'process', [], 'same.route'),
            ])
        ;

        $container
            ->expects(self::never())
            ->method('get')
        ;

        $collector
            ->expects(self::once())
            ->method('route')
            ->with(
                '/same',
                self::callback(static fn (mixed $registered): bool => $registered instanceof MiddlewareInterface),
                ['GET'],
                'same.route'
            )
            ->willReturnCallback(static fn (
                string $path,
                MiddlewareInterface $registered,
                ?array $methods,
                ?string $name
            ): Route => new Route('/same', $registered, ['GET'], 'same.route'))
        ;

        $provider = new AttributeRouteProvider(
            $container,
            $extractor,
            [TestRequestHandler::class],
            AttributeRouteProvider::DUPLICATE_STRATEGY_IGNORE
        );
        $provider->registerRoutes($collector);
    }

    public function testThrowsWhenContainerServiceTypeIsInvalid(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $collector = $this->createMock(RouteCollectorInterface::class);
        $registeredMiddleware = null;

        $extractor
            ->expects(self::once())
            ->method('extract')
            ->with([TestRequestHandler::class])
            ->willReturn([
                new RouteDefinition('/broken', ['GET'], 'broken.service', 'process', [], 'broken.route'),
            ])
        ;

        $container
            ->expects(self::once())
            ->method('get')
            ->with('broken.service')
            ->willReturn(42)
        ;

        $collector
            ->expects(self::once())
            ->method('route')
            ->willReturnCallback(function(
                string $path,
                MiddlewareInterface $middleware,
                ?array $methods,
                ?string $name
            ) use (&$registeredMiddleware): Route {
                $registeredMiddleware = $middleware;

                return new Route('/broken', $middleware, ['GET'], 'broken.route');
            })
        ;

        $provider = new AttributeRouteProvider($container, $extractor, [TestRequestHandler::class]);
        $provider->registerRoutes($collector);

        self::assertInstanceOf(MiddlewareInterface::class, $registeredMiddleware);
        $this->expectException(InvalidServiceDefinitionException::class);
        $registeredMiddleware->process(
            $this->createMock(ServerRequestInterface::class),
            $this->createMock(RequestHandlerInterface::class)
        );
    }

    public function testThrowsWhenHandlerMethodDoesNotExist(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $collector = $this->createMock(RouteCollectorInterface::class);
        $registeredMiddleware = null;
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new RuntimeException('Not used in this test.');
            }
        };

        $extractor
            ->expects(self::once())
            ->method('extract')
            ->with([TestRequestHandler::class])
            ->willReturn([
                new RouteDefinition('/broken-method', ['GET'], 'handler.service', 'missingMethod', [], 'broken.method.route'),
            ])
        ;

        $container
            ->expects(self::once())
            ->method('get')
            ->with('handler.service')
            ->willReturn($handler)
        ;

        $collector
            ->expects(self::once())
            ->method('route')
            ->willReturnCallback(function(
                string $path,
                MiddlewareInterface $middleware,
                ?array $methods,
                ?string $name
            ) use (&$registeredMiddleware): Route {
                $registeredMiddleware = $middleware;

                return new Route('/broken-method', $middleware, ['GET'], 'broken.method.route');
            })
        ;

        $provider = new AttributeRouteProvider($container, $extractor, [TestRequestHandler::class]);
        $provider->registerRoutes($collector);

        self::assertInstanceOf(MiddlewareInterface::class, $registeredMiddleware);
        $this->expectException(InvalidServiceDefinitionException::class);
        $registeredMiddleware->process(
            $this->createMock(ServerRequestInterface::class),
            $this->createMock(RequestHandlerInterface::class)
        );
    }

    public function testThrowsWhenTerminalMethodReturnsNonResponse(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $collector = $this->createMock(RouteCollectorInterface::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $fallbackHandler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new RuntimeException('Pipeline should not delegate in this test.');
            }
        };
        $service = new class {
            public function terminal(ServerRequestInterface $request): string
            {
                return 'not-a-response';
            }
        };

        $extractor
            ->expects(self::once())
            ->method('extract')
            ->with([TestRequestHandler::class])
            ->willReturn([
                new RouteDefinition('/bad-return', ['GET'], 'terminal.service', 'terminal', [], 'bad.return.route'),
            ])
        ;

        $container
            ->expects(self::once())
            ->method('get')
            ->with('terminal.service')
            ->willReturn($service)
        ;

        $collector
            ->expects(self::once())
            ->method('route')
            ->willReturnCallback(static function(
                string $path,
                MiddlewareInterface $middleware,
                ?array $methods,
                ?string $name
            ) use ($request, $fallbackHandler): Route {
                $middleware->process($request, $fallbackHandler);

                return new Route('/bad-return', $middleware, ['GET'], 'bad.return.route');
            })
        ;

        $this->expectException(InvalidServiceDefinitionException::class);

        $provider = new AttributeRouteProvider($container, $extractor, [TestRequestHandler::class]);
        $provider->registerRoutes($collector);
    }

    public function testInvokesPublicMethodOnPlainServiceClass(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $collector = $this->createMock(RouteCollectorInterface::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $fallbackHandler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new RuntimeException('Pipeline should not delegate in this test.');
            }
        };
        $service = new class($response) {
            public function __construct(private readonly ResponseInterface $response) {}

            public function index(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        $extractor
            ->expects(self::once())
            ->method('extract')
            ->with([TestRequestHandler::class])
            ->willReturn([
                new RouteDefinition('/callable', ['GET'], 'callable.service', 'index', [], 'callable.route'),
            ])
        ;

        $container
            ->expects(self::once())
            ->method('get')
            ->with('callable.service')
            ->willReturn($service)
        ;

        $collector
            ->expects(self::once())
            ->method('route')
            ->willReturnCallback(static function(
                string $path,
                MiddlewareInterface $middleware,
                ?array $methods,
                ?string $name
            ) use ($request, $fallbackHandler, $response): Route {
                self::assertSame($response, $middleware->process($request, $fallbackHandler));

                return new Route('/callable', $middleware, ['GET'], 'callable.route');
            })
        ;

        $provider = new AttributeRouteProvider($container, $extractor, [TestRequestHandler::class]);
        $provider->registerRoutes($collector);
    }

    public function testUsesCompiledCacheFileWhenAvailable(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $collector = $this->createMock(RouteCollectorInterface::class);
        $cacheFile = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        $compiledCache = new CompiledRouteRegistrarCache($cacheFile);
        $compiledCache->save([
            new RouteDefinition('/compiled', ['GET'], 'compiled.service', 'process', [], 'compiled.route'),
        ]);

        $extractor
            ->expects(self::never())
            ->method('extract')
        ;

        $container
            ->expects(self::never())
            ->method('get')
        ;

        $collector
            ->expects(self::once())
            ->method('route')
            ->with(
                '/compiled',
                self::callback(static fn (mixed $registered): bool => $registered instanceof MiddlewareInterface),
                ['GET'],
                'compiled.route'
            )
            ->willReturnCallback(static fn (
                string $path,
                MiddlewareInterface $registered,
                ?array $methods,
                ?string $name
            ): Route => new Route('/compiled', $registered, ['GET'], 'compiled.route'))
        ;

        $provider = new AttributeRouteProvider(
            $container,
            $extractor,
            [TestMiddleware::class],
            AttributeRouteProvider::DUPLICATE_STRATEGY_THROW,
            null,
            new MiddlewarePipelineFactory($container),
            $compiledCache
        );
        $provider->registerRoutes($collector);
    }

    private function createCacheFilePath(): string
    {
        return sys_get_temp_dir() . '/mezzio-routing-attributes-test-' . uniqid('', true) . '.php';
    }
}
