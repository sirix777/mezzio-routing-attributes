<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Integration;

use Laminas\Diactoros\ServerRequest;
use Mezzio\Router\Exception\DuplicateRouteException;
use Mezzio\Router\RouteCollector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sirix\Mezzio\Router\RadixRouter;
use Sirix\Mezzio\Routing\Attributes\AttributeRouteProvider;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;
use Sirix\Mezzio\Routing\Attributes\Discovery\NullDiscoveredClassesResolver;
use Sirix\Mezzio\Routing\Attributes\DuplicateRouteResolver;
use Sirix\Mezzio\Routing\Attributes\Exception\DuplicateRouteDefinitionException;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;
use Sirix\Mezzio\Routing\Attributes\Factory\CompiledRouteRegistrarCacheFactory;
use Sirix\Mezzio\Routing\Attributes\MiddlewarePipelineFactory;
use Sirix\Mezzio\Routing\Attributes\RouteCacheWarmer;
use Sirix\Mezzio\Routing\Attributes\RouteDefinition;
use Sirix\Mezzio\Routing\Attributes\ServiceMiddlewareResolver;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\InMemoryContainer;
use SirixTest\Mezzio\Routing\Attributes\TestMiddleware;

use function array_reverse;
use function is_file;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class EffectiveRouteNameTest extends TestCase
{
    /** @param 'ignore'|'throw' $strategy */
    #[TestWith([false, 'ignore'])]
    #[TestWith([true, 'ignore'])]
    #[TestWith([false, 'throw'])]
    #[TestWith([true, 'throw'])]
    public function testOverlappingPathsWithDistinctNames(bool $warm, string $strategy): void
    {
        $router    = new RadixRouter();
        $collector = new RouteCollector($router);
        $routes    = [
            new RouteDefinition('/overlap', ['GET', 'POST'], TestMiddleware::class, 'process', [], 'first'),
            new RouteDefinition('/overlap', ['POST'], TestMiddleware::class, 'process', [], 'second'),
        ];
        if ('throw' === $strategy) {
            $this->expectException(DuplicateRouteDefinitionException::class);
        }
        $this->register($routes, $strategy, $warm, $collector);
        self::assertCount(1, $collector->getRoutes());
        $match = $router->match(new ServerRequest(uri: '/overlap', method: 'POST'));
        self::assertTrue($match->isSuccess());
        self::assertSame('first', $match->getMatchedRouteName());
    }

    /**
     * @param null|list<non-empty-string> $methods
     * @param non-empty-string            $name
     */
    #[DataProvider('collisions')]
    public function testIgnoreKeepsFirstAndRegistersWithRealCollector(?array $methods, string $name, bool $reverse, bool $warm): void
    {
        $routes    = $this->routes($methods, $name, $reverse);
        $collector = $this->register($routes, 'ignore', $warm);

        self::assertCount(1, $collector->getRoutes());
        self::assertSame($routes[0]->path, $collector->getRoutes()[0]->getPath());
        self::assertSame($name, $collector->getRoutes()[0]->getName());
    }

    /**
     * @param null|list<non-empty-string> $methods
     * @param non-empty-string            $name
     */
    #[DataProvider('collisions')]
    public function testThrowRejectsBeforeRegistrationOrCacheWrite(?array $methods, string $name, bool $reverse, bool $warm): void
    {
        $this->expectException(DuplicateRouteDefinitionException::class);
        $this->expectExceptionMessage($name);
        $this->register($this->routes($methods, $name, $reverse), 'throw', $warm);
    }

    /** @return iterable<string, array{null|list<non-empty-string>, non-empty-string, bool, bool}> */
    public static function collisions(): iterable
    {
        foreach ([true, false] as $reverse) {
            foreach ([true, false] as $warm) {
                foreach ([
                    'get'            => [['GET'], '/a^GET'],
                    'any'            => [null, '/a'],
                    'case-and-order' => [['post', 'get'], '/a^POST:GET'],
                ] as $case => [$methods, $name]) {
                    yield $case . '-' . (int) $reverse . '-' . (int) $warm => [$methods, $name, $reverse, $warm];
                }
            }
        }
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testDifferentMethodOrderDoesNotCauseFalseNameCollision(bool $warm): void
    {
        $routes    = $this->routes(['post', 'get'], '/a^GET:POST', false);
        $collector = $this->register($routes, 'throw', $warm);

        self::assertCount(2, $collector->getRoutes());
        self::assertSame('/a^POST:GET', $collector->getRoutes()[0]->getName());
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testClassicRouteStillUsesMezzioDuplicatePolicy(bool $warm): void
    {
        $collector = new RouteCollector(new RadixRouter());
        $collector->get('/classic', new TestMiddleware(), '/a^GET');

        $this->expectException(DuplicateRouteException::class);

        try {
            $this->register([$this->routes(['GET'], '/a^GET', false)[0]], 'ignore', $warm, $collector);
        } catch (RuntimeException $exception) {
            if (! $warm) {
                throw $exception;
            }

            self::assertInstanceOf(DuplicateRouteException::class, $exception->getPrevious());

            throw $exception->getPrevious();
        }
    }

    /**
     * @param null|list<non-empty-string> $methods
     * @param non-empty-string            $name
     *
     * @return list<RouteDefinition>
     */
    private function routes(?array $methods, string $name, bool $reverse): array
    {
        $routes = [
            new RouteDefinition('/a', $methods, TestMiddleware::class, 'process', []),
            new RouteDefinition('/b', ['GET'], TestMiddleware::class, 'process', [], $name),
        ];

        return $reverse ? array_reverse($routes) : $routes;
    }

    /**
     * @param list<RouteDefinition> $routes
     * @param 'ignore'|'throw'      $strategy
     */
    private function register(array $routes, string $strategy, bool $warm, ?RouteCollector $collector = null): RouteCollector
    {
        $file = tempnam(sys_get_temp_dir(), 'effective-route-name-');
        self::assertIsString($file);
        unlink($file);

        try {
            $cache     = (new CompiledRouteRegistrarCacheFactory())->createFromCacheFile($warm ? $file : null);
            $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
            $extractor->expects(self::once())->method('extract')->willReturn($routes);
            $resolver = new DuplicateRouteResolver($strategy);
            if ($warm) {
                $warmer = new RouteCacheWarmer(
                    RoutingAttributesConfig::fromRootConfig([]),
                    $extractor,
                    $resolver,
                    new NullDiscoveredClassesResolver(),
                    $cache
                );
                self::assertTrue($warmer->warm());
            }
            $provider = new AttributeRouteProvider(
                $extractor,
                [],
                $resolver,
                new MiddlewarePipelineFactory(new InMemoryContainer([]), new ServiceMiddlewareResolver()),
                $cache
            );
            $collector ??= new RouteCollector(new RadixRouter());
            $provider->registerRoutes($collector);

            return $collector;
        } catch (DuplicateRouteDefinitionException $exception) {
            self::assertFileDoesNotExist($file);

            throw $exception;
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
