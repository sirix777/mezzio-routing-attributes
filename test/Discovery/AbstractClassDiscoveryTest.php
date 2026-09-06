<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Discovery;

use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveryClassMapResolver;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveryFileInventory;
use Sirix\Mezzio\Routing\Attributes\Discovery\NullDiscoveredClassesResolver;
use Sirix\Mezzio\Routing\Attributes\Discovery\PhpClassNameParser;
use Sirix\Mezzio\Routing\Attributes\Discovery\Psr4ClassNameResolver;
use Sirix\Mezzio\Routing\Attributes\Discovery\RoutableClassFilter;
use Sirix\Mezzio\Routing\Attributes\DuplicateRouteResolver;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractor;
use Sirix\Mezzio\Routing\Attributes\Extractor\ClassEligibilityValidator;
use Sirix\Mezzio\Routing\Attributes\Extractor\MethodSignatureValidator;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteAttributeReader;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteDataNormalizer;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteDefinitionBuilder;
use Sirix\Mezzio\Routing\Attributes\MiddlewarePipelineFactory;
use Sirix\Mezzio\Routing\Attributes\RouteDefinitionResolver;
use Sirix\Mezzio\Routing\Attributes\RouteRegistrar;
use Sirix\Mezzio\Routing\Attributes\ServiceMiddlewareResolver;
use SirixTest\Mezzio\Routing\Attributes\Discovery\Fixture\AbstractRoutes\Callable as CallableRoutes;
use SirixTest\Mezzio\Routing\Attributes\Discovery\Fixture\AbstractRoutes\Psr15;
use SirixTest\Mezzio\Routing\Attributes\Discovery\Fixture\PrivateConstructorHandler;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\InMemoryContainer;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\RecordingRouteCollector;

final class AbstractClassDiscoveryTest extends TestCase
{
    /**
     * @param 'ignore'|'throw' $duplicates
     * @param 'psr4'|'token'   $strategy
     */
    #[DataProvider('discoveryModes')]
    public function testOnlyConcreteInheritedRouteIsRegistered(bool $allowCallable, string $duplicates, string $strategy): void
    {
        $mode          = $allowCallable ? 'Callable' : 'Psr15';
        $path          = __DIR__ . '/Fixture/AbstractRoutes/' . $mode;
        $concreteClass = $allowCallable ? CallableRoutes\ConcreteHandler::class : Psr15\ConcreteHandler::class;
        $discovery     = new DiscoveryClassMapResolver(
            $strategy,
            false,
            new DiscoveryFileInventory([$path]),
            new PhpClassNameParser(),
            new Psr4ClassNameResolver([
                $path => __NAMESPACE__ . '\Fixture\AbstractRoutes\\' . $mode . '\\',
            ]),
            new RoutableClassFilter($allowCallable)
        );
        $resolver = new RouteDefinitionResolver(
            $this->createExtractor($allowCallable),
            new DuplicateRouteResolver($duplicates),
            $discovery
        );

        $routes = $resolver->resolve([]);

        self::assertCount(1, $routes);
        self::assertSame($concreteClass, $routes[0]->handlerService);
        self::assertSame([$concreteClass], $discovery->resolve());
        $collector = new RecordingRouteCollector();
        (new RouteRegistrar())->register($collector, $routes, new MiddlewarePipelineFactory(
            new InMemoryContainer([
                $concreteClass => new $concreteClass(),
            ]),
            new ServiceMiddlewareResolver()
        ));
        self::assertCount(1, $collector->routes);
        $response = $collector->routes[0]->getMiddleware()->process(
            new ServerRequest(),
            $this->createMock(RequestHandlerInterface::class)
        );
        self::assertSame($concreteClass, $response->getHeaderLine('X-Handler'));
    }

    /**
     * @return iterable<string, array{bool, 'ignore'|'throw', 'psr4'|'token'}>
     */
    public static function discoveryModes(): iterable
    {
        foreach ([false, true] as $allowCallable) {
            foreach (['throw', 'ignore'] as $duplicates) {
                foreach (['token', 'psr4'] as $strategy) {
                    yield ($allowCallable ? 'callable' : 'psr15') . '-' . $duplicates . '-' . $strategy => [$allowCallable, $duplicates, $strategy];
                }
            }
        }
    }

    public function testExplicitAbstractContainerBindingsRemainSupported(): void
    {
        foreach ([false, true] as $allowCallable) {
            $abstractClass = $allowCallable ? CallableRoutes\AbstractHandler::class : Psr15\AbstractHandler::class;
            $concreteClass = $allowCallable ? CallableRoutes\ConcreteHandler::class : Psr15\ConcreteHandler::class;
            $resolver      = new RouteDefinitionResolver(
                $this->createExtractor($allowCallable),
                new DuplicateRouteResolver('throw'),
                new NullDiscoveredClassesResolver()
            );
            $routes = $resolver->resolve([$abstractClass]);
            self::assertCount(1, $routes);
            self::assertSame($abstractClass, $routes[0]->handlerService);
            $pipeline = new MiddlewarePipelineFactory(
                new InMemoryContainer([
                    $abstractClass => new $concreteClass(),
                ]),
                new ServiceMiddlewareResolver()
            );
            $response = $pipeline->createFromSignature($routes[0]->handlerService, $routes[0]->handlerMethod, [])->process(
                new ServerRequest(),
                $this->createMock(RequestHandlerInterface::class)
            );
            self::assertSame($concreteClass, $response->getHeaderLine('X-Handler'));
        }
    }

    public function testPrivateConstructorDoesNotExcludeFactoryCreatedHandlers(): void
    {
        foreach ([false, true] as $allowCallable) {
            self::assertSame([PrivateConstructorHandler::class], (new RoutableClassFilter($allowCallable))->filter([
                PrivateConstructorHandler::class,
            ]));
        }
    }

    private function createExtractor(bool $allowCallable): AttributeRouteExtractor
    {
        return new AttributeRouteExtractor(
            new ClassEligibilityValidator($allowCallable),
            new RouteAttributeReader(),
            new RouteDefinitionBuilder(new RouteAttributeReader(), new MethodSignatureValidator(), new RouteDataNormalizer())
        );
    }
}
