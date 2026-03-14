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
use Sirix\Mezzio\Routing\Attributes\Exception\DuplicateRouteDefinitionException;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidServiceDefinitionException;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;
use Sirix\Mezzio\Routing\Attributes\RouteDefinition;

use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function str_contains;
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
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };

        $extractor
            ->expects(self::once())
            ->method('extract')
            ->with([TestMiddleware::class])
            ->willReturn([
                new RouteDefinition('/test', ['GET'], TestMiddleware::class, 'process', [], 'test.route'),
            ])
        ;

        $container
            ->expects(self::once())
            ->method('get')
            ->with(TestMiddleware::class)
            ->willReturn($middleware)
        ;

        $collector
            ->expects(self::once())
            ->method('route')
            ->with('/test', $middleware, ['GET'], 'test.route')
            ->willReturn(new Route('/test', $middleware, ['GET'], 'test.route'))
        ;

        $provider = new AttributeRouteProvider($container, $extractor, [TestMiddleware::class]);
        $provider->registerRoutes($collector);
    }

    public function testWrapsRequestHandlerServiceAsMiddleware(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $collector = $this->createMock(RouteCollectorInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $requestHandler = new class($response) implements RequestHandlerInterface {
            public function __construct(private readonly ResponseInterface $response) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        $extractor
            ->expects(self::once())
            ->method('extract')
            ->with([TestRequestHandler::class])
            ->willReturn([
                new RouteDefinition('/handler', ['GET'], TestRequestHandler::class, 'handle', [], 'handler.route'),
            ])
        ;

        $container
            ->expects(self::once())
            ->method('get')
            ->with(TestRequestHandler::class)
            ->willReturn($requestHandler)
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
            ->expects(self::exactly(3))
            ->method('get')
            ->willReturnMap([
                ['first.service', $first],
                ['second.service', $second],
                ['handler.service', $finalHandler],
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
        $handler = $this->createMock(RequestHandlerInterface::class);
        $middleware = new class($handler) implements MiddlewareInterface {
            public function __construct(private readonly RequestHandlerInterface $handler) {}

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $this->handler->handle($request);
            }
        };

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
            ->expects(self::once())
            ->method('get')
            ->with('first.service')
            ->willReturn($middleware)
        ;

        $collector
            ->expects(self::once())
            ->method('route')
            ->with('/same', $middleware, ['GET'], 'same.route')
            ->willReturn(new Route('/same', $middleware, ['GET'], 'same.route'))
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
            ->expects(self::never())
            ->method('route')
        ;

        $this->expectException(InvalidServiceDefinitionException::class);

        $provider = new AttributeRouteProvider($container, $extractor, [TestRequestHandler::class]);
        $provider->registerRoutes($collector);
    }

    public function testThrowsWhenHandlerMethodDoesNotExist(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $collector = $this->createMock(RouteCollectorInterface::class);
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
            ->expects(self::never())
            ->method('route')
        ;

        $this->expectException(InvalidServiceDefinitionException::class);

        $provider = new AttributeRouteProvider($container, $extractor, [TestRequestHandler::class]);
        $provider->registerRoutes($collector);
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

    public function testUsesCacheFileWhenAvailable(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $collector = $this->createMock(RouteCollectorInterface::class);
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };
        $cacheFile = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;
        file_put_contents(
            $cacheFile,
            <<<'PHP'
                <?php

                declare(strict_types=1);

                return [
                    [
                        0 => '/cached',
                        1 => ['GET'],
                        2 => 'cached.service',
                        3 => 'process',
                        4 => [],
                        5 => 'cached.route',
                    ],
                ];
                PHP
        );

        $extractor
            ->expects(self::never())
            ->method('extract')
        ;

        $container
            ->expects(self::once())
            ->method('get')
            ->with('cached.service')
            ->willReturn($middleware)
        ;

        $collector
            ->expects(self::once())
            ->method('route')
            ->with('/cached', $middleware, ['GET'], 'cached.route')
            ->willReturn(new Route('/cached', $middleware, ['GET'], 'cached.route'))
        ;

        $provider = new AttributeRouteProvider(
            $container,
            $extractor,
            [TestMiddleware::class],
            AttributeRouteProvider::DUPLICATE_STRATEGY_THROW,
            $cacheFile
        );
        $provider->registerRoutes($collector);
    }

    public function testWritesCacheFileWhenEnabledAndMissing(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $collector = $this->createMock(RouteCollectorInterface::class);
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };
        $cacheFile = $this->createCacheFilePath();
        $this->cacheFiles[] = $cacheFile;

        $extractor
            ->expects(self::once())
            ->method('extract')
            ->with([TestMiddleware::class])
            ->willReturn([
                new RouteDefinition('/fresh', ['GET'], 'fresh.service', 'process', [], 'fresh.route'),
            ])
        ;

        $container
            ->expects(self::once())
            ->method('get')
            ->with('fresh.service')
            ->willReturn($middleware)
        ;

        $collector
            ->expects(self::once())
            ->method('route')
            ->with('/fresh', $middleware, ['GET'], 'fresh.route')
            ->willReturn(new Route('/fresh', $middleware, ['GET'], 'fresh.route'))
        ;

        $provider = new AttributeRouteProvider(
            $container,
            $extractor,
            [TestMiddleware::class],
            AttributeRouteProvider::DUPLICATE_STRATEGY_THROW,
            $cacheFile
        );
        $provider->registerRoutes($collector);

        self::assertTrue(file_exists($cacheFile));
        self::assertTrue(str_contains((string) file_get_contents($cacheFile), 'fresh.route'));
    }

    private function createCacheFilePath(): string
    {
        return sys_get_temp_dir() . '/mezzio-routing-attributes-test-' . uniqid('', true) . '.php';
    }
}
