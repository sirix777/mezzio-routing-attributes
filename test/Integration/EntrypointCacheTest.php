<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Integration;

use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\AttributeRouteProvider;
use Sirix\Mezzio\Routing\Attributes\Cache\NullRouteRegistrarCache;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheGenerator;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheLoader;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheStorage;
use Sirix\Mezzio\Routing\Attributes\CompiledRouteRegistrarCache;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;
use Sirix\Mezzio\Routing\Attributes\Discovery\NullDiscoveredClassesResolver;
use Sirix\Mezzio\Routing\Attributes\DuplicateRouteResolver;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractor;
use Sirix\Mezzio\Routing\Attributes\Extractor\ClassEligibilityValidator;
use Sirix\Mezzio\Routing\Attributes\Extractor\MethodSignatureValidator;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteAttributeReader;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteDataNormalizer;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteDefinitionBuilder;
use Sirix\Mezzio\Routing\Attributes\MiddlewarePipelineFactory;
use Sirix\Mezzio\Routing\Attributes\RouteCacheWarmer;
use Sirix\Mezzio\Routing\Attributes\ServiceMiddlewareResolver;
use SirixTest\Mezzio\Routing\Attributes\Integration\Fixture\BothInterfacesHandler;
use SirixTest\Mezzio\Routing\Attributes\Integration\Fixture\PlainRequestHandler;
use SirixTest\Mezzio\Routing\Attributes\Integration\Fixture\PrivateHelperMiddleware;
use SirixTest\Mezzio\Routing\Attributes\Integration\Fixture\PublicHelperMiddleware;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\RecordingRouteCollector;

use function is_file;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class EntrypointCacheTest extends TestCase
{
    /** @param class-string $class */
    #[DataProvider('entrypoints')]
    public function testInterfaceSelectsLazyEntrypoint(string $class, int $status, bool $cached): void
    {
        $file      = sys_get_temp_dir() . '/entrypoint-' . uniqid('', true) . '.php';
        $extractor = new AttributeRouteExtractor(
            new ClassEligibilityValidator(false),
            new RouteAttributeReader(),
            new RouteDefinitionBuilder(new RouteAttributeReader(), new MethodSignatureValidator(), new RouteDataNormalizer())
        );
        $cache = $cached
            ? new CompiledRouteRegistrarCache($file, new RouteCacheGenerator(), new RouteCacheStorage(), new RouteCacheLoader())
            : new NullRouteRegistrarCache();
        $resolved  = false;
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('get')->with($class)->willReturnCallback(static function() use ($class, &$resolved): object {
            $resolved = true;

            return new $class();
        });

        try {
            if ($cached) {
                $warmer = new RouteCacheWarmer(RoutingAttributesConfig::fromRootConfig([
                    'routing_attributes' => [
                        'classes' => [$class],
                    ],
                ]), $extractor, new DuplicateRouteResolver('throw'), new NullDiscoveredClassesResolver(), $cache);
                self::assertTrue($warmer->warm());
            }
            $provider  = new AttributeRouteProvider(
                $extractor,
                [$class],
                new DuplicateRouteResolver('throw'),
                new MiddlewarePipelineFactory($container, new ServiceMiddlewareResolver()),
                $cache
            );
            $collector = new RecordingRouteCollector();
            $provider->registerRoutes($collector);
            self::assertFalse($resolved);
            self::assertCount(1, $collector->routes);
            $fallback = $this->createMock(RequestHandlerInterface::class);
            $fallback->expects(self::never())->method('handle');
            self::assertSame($status, $collector->routes[0]->getMiddleware()->process(new ServerRequest(), $fallback)->getStatusCode());
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /** @return iterable<string, array{class-string, int, bool}> */
    public static function entrypoints(): iterable
    {
        foreach ([false, true] as $cached) {
            foreach ([
                PrivateHelperMiddleware::class => 201,
                PublicHelperMiddleware::class  => 201,
                PlainRequestHandler::class     => 202,
                BothInterfacesHandler::class   => 202,
            ] as $class => $status) {
                yield $class . ($cached ? ' cached' : ' uncached') => [$class, $status, $cached];
            }
        }
    }
}
