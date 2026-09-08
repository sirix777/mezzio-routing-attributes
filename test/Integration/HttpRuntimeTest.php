<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Integration;

use Laminas\Diactoros\ServerRequest;
use Laminas\ServiceManager\ServiceManager;
use Mezzio\Router\Middleware\DispatchMiddleware;
use Mezzio\Router\Middleware\RouteMiddleware;
use Mezzio\Router\RouteCollector;
use Mezzio\Router\RouterInterface;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Router\RadixRouterFactory;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteRegistrarCacheInterface;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;
use Sirix\Mezzio\Routing\Attributes\ConfigProvider;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveredClassesResolverInterface;
use Sirix\Mezzio\Routing\Attributes\DuplicateRouteResolver;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteDataNormalizer;
use Sirix\Mezzio\Routing\Attributes\MiddlewareHandler;
use Sirix\Mezzio\Routing\Attributes\RouteCacheWarmer;
use SirixTest\Mezzio\Routing\Attributes\Integration\Fixture\HttpActions;
use SirixTest\Mezzio\Routing\Attributes\Integration\Fixture\HttpDownstream;
use SirixTest\Mezzio\Routing\Attributes\Integration\Fixture\HttpHandler;
use SirixTest\Mezzio\Routing\Attributes\Integration\Fixture\HttpMiddleware;
use SirixTest\Mezzio\Routing\Attributes\Integration\Fixture\HttpPrefixActions;
use SirixTest\Mezzio\Routing\Attributes\Integration\Fixture\HttpSpecificationFactory;
use SirixTest\Mezzio\Routing\Attributes\Integration\Fixture\HttpTrace;

use function is_file;
use function json_decode;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class HttpRuntimeTest extends TestCase
{
    /** @var list<string> */
    private array $cacheFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->cacheFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testHandlerPipelineAndLazyInstanceReuse(bool $warm): void
    {
        [$container, $trace] = $this->application($warm);
        $router              = $container->get(RouterInterface::class);
        self::assertSame([], $trace->created);
        $downstream = $this->downstream('unused');

        foreach (['first', 'second'] as $id) {
            $trace->events = [];
            $request       = (new ServerRequest(uri: '/handler/' . $id, method: 'GET'))->withAttribute('input', $id);
            $response      = $this->dispatch($router, $request, $downstream);
            self::assertSame(201, $response->getStatusCode());
            self::assertSame([
                'id'            => $id,
                'input'         => $id,
                'service'       => $id,
                'specification' => 'configured',
            ], json_decode((string) $response->getBody(), true));
            self::assertSame('executed', $response->getHeaderLine('X-Service'));
            self::assertSame('executed', $response->getHeaderLine('X-Specification'));
            self::assertSame(['service:before', 'specification:before', 'handler', 'specification:after', 'service:after'], $trace->events);
            self::assertNull($request->getAttribute('service'));
        }
        self::assertSame([], $downstream->requests);
        self::assertSame([
            HttpMiddleware::class           => 1,
            HttpSpecificationFactory::class => 1,
            'specification-result'          => 1,
            HttpHandler::class              => 1,
        ], $trace->created);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testShortCircuitDoesNotResolveRemainingServices(bool $warm): void
    {
        [$container, $trace] = $this->application($warm);
        $downstream          = $this->downstream('unused');
        $request             = (new ServerRequest(uri: '/handler/1', method: 'GET'))->withAttribute('stop', true);
        $response            = $this->dispatch($container->get(RouterInterface::class), $request, $downstream);
        self::assertSame(202, $response->getStatusCode());
        self::assertSame('stopped', (string) $response->getBody());
        self::assertSame([
            HttpMiddleware::class => 1,
        ], $trace->created);
        self::assertSame(['service:before'], $trace->events);
        self::assertSame([], $downstream->requests);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testCallableAndClassMiddlewareReusePipelineWithFreshDownstream(bool $warm): void
    {
        [$container] = $this->application($warm);
        $router      = $container->get(RouterInterface::class);
        foreach (['/action', '/middleware'] as $path) {
            foreach (['first', 'second'] as $input) {
                $downstream = $this->downstream($input);
                $request    = (new ServerRequest(uri: $path, method: 'GET'))->withAttribute('input', $input)->withAttribute('repeat', true);
                $response   = $this->dispatch($router, $request, $downstream);
                self::assertSame([
                    'downstream' => $input,
                    'service'    => 'second',
                    'action'     => '/action' === $path ? true : null,
                ], json_decode((string) $response->getBody(), true));
                self::assertCount(2, $downstream->requests);
                self::assertSame($input, $downstream->requests[0]->getAttribute('service'));
                self::assertSame('second', $downstream->requests[1]->getAttribute('service'));
                self::assertNull($request->getAttribute('action'));
                self::assertNull($request->getAttribute('service'));
                self::assertSame('/action' === $path ? 'executed' : '', $response->getHeaderLine('X-Action'));
            }
        }
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testAdapterOptionsOptionalPlaceholdersAndNoMatch(bool $warm): void
    {
        [$container] = $this->application($warm);
        $router      = $container->get(RouterInterface::class);
        $routes      = $container->get(RouteCollector::class)->getRoutes();
        $optional    = $routes[3];
        self::assertSame('http.optional', $optional->getName());
        self::assertSame([
            'id' => 'default-id',
        ], $optional->getOptions()['defaults']);
        self::assertSame('option-only', $optional->getOptions()['plain']);
        foreach ([
            '/optional'     => 'default-id',
            '/optional/123' => '123',
        ] as $path => $id) {
            $response = $this->dispatch($router, new ServerRequest(uri: $path, method: 'GET'), $this->downstream('not-found'));
            self::assertSame([
                'id'    => $id,
                'plain' => null,
            ], json_decode((string) $response->getBody(), true));
        }
        self::assertSame('/optional', $router->generateUri('http.optional'));
        self::assertSame('/optional/123', $router->generateUri('http.optional', [
            'id' => '123',
        ]));
        foreach ([['/missing', 'GET'], ['/handler/1', 'POST']] as [$path, $method]) {
            $downstream = $this->downstream('not-found');
            $response   = $this->dispatch($router, new ServerRequest(uri: $path, method: $method), $downstream);
            self::assertSame(404, $response->getStatusCode());
            self::assertCount(1, $downstream->requests);
        }
    }

    public function testClassPrefixesPreserveMethodMetadataAndModifierOrder(): void
    {
        [$container] = $this->application(false);
        $routes      = $container->get(AttributeRouteExtractorInterface::class)->extract([HttpPrefixActions::class]);

        self::assertCount(2, $routes);
        foreach ([['/api/v1/orders/', 'orders.list'], ['/api/v1/', 'orders.root']] as $index => [$path, $name]) {
            self::assertSame($path, $routes[$index]->path);
            self::assertSame($name, $routes[$index]->name);
            self::assertSame(['GET'], $routes[$index]->methods);
            self::assertSame(HttpPrefixActions::class, $routes[$index]->handlerService);
            self::assertSame('orders', $routes[$index]->handlerMethod);
            self::assertSame([
                'class.first', 'class.second', 'method.route', 'class.modifier', 'method.modifier',
            ], $routes[$index]->middlewareServices);
            self::assertSame([
                'scope'      => 'method',
                'classOnly'  => true,
                'methodOnly' => true,
            ], $routes[$index]->defaults);
        }
    }

    /** @param non-empty-string $path */
    #[TestWith(['/'])]
    #[TestWith(['orders/'])]
    #[TestWith(['/orders/'])]
    public function testRootOnlyClassPrefixPreservesMethodPath(string $path): void
    {
        self::assertSame($path, (new RouteDataNormalizer())->prependClassPrefixes(HttpPrefixActions::class, $path, [new Get('/')]));
    }

    /** @return array{ServiceManager, HttpTrace} */
    private function application(bool $warm): array
    {
        $file = tempnam(sys_get_temp_dir(), 'http-routing-');
        self::assertIsString($file);
        $this->cacheFiles[] = $file;
        unlink($file);
        $trace                        = new HttpTrace();
        $config                       = (new ConfigProvider())();
        $config['routing_attributes'] = [
            'classes'  => [HttpHandler::class, HttpMiddleware::class, HttpActions::class],
            'handlers' => [
                'mode' => 'callable',
            ],
            'cache'    => [
                'enabled' => $warm,
                'file'    => $file,
            ],
        ];
        $dependencies                                      = $config['dependencies'];
        $dependencies['services']['config']                = $config;
        $dependencies['factories'][RouterInterface::class] = RadixRouterFactory::class;
        $dependencies['factories'][RouteCollector::class]  = static function(ContainerInterface $container): RouteCollector {
            $router = $container->get(RouterInterface::class);

            return new RouteCollector($router);
        };
        foreach ([HttpHandler::class, HttpMiddleware::class, HttpActions::class, HttpSpecificationFactory::class] as $class) {
            $dependencies['factories'][$class] = static function() use ($class, $trace): object {
                $trace->created[$class] = ($trace->created[$class] ?? 0) + 1;

                return HttpActions::class === $class ? new HttpActions() : new $class($trace);
            };
        }
        $container = new ServiceManager($dependencies);
        if ($warm) {
            $warmer = new RouteCacheWarmer(
                $container->get(RoutingAttributesConfig::class),
                $container->get(AttributeRouteExtractorInterface::class),
                $container->get(DuplicateRouteResolver::class),
                $container->get(DiscoveredClassesResolverInterface::class),
                $container->get(RouteRegistrarCacheInterface::class)
            );
            self::assertTrue($warmer->warm());
            self::assertFileExists($file);
            self::assertSame([], $trace->created);
            // A fresh container must consume the warmed artifact, not an in-memory pipeline.
            $container = new ServiceManager($dependencies);
            $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
            $extractor->expects(self::never())->method('extract');
            $container->setService(AttributeRouteExtractorInterface::class, $extractor);
        }
        self::assertCount(4, $container->get(RouteCollector::class)->getRoutes());
        self::assertSame([], $trace->created);

        return [$container, $trace];
    }

    private function dispatch(
        RouterInterface $router,
        ServerRequestInterface $request,
        RequestHandlerInterface $downstream
    ): ResponseInterface {
        return (new RouteMiddleware($router))->process($request, new MiddlewareHandler(new DispatchMiddleware(), $downstream));
    }

    private function downstream(string $label): HttpDownstream
    {
        return new HttpDownstream($label);
    }
}
