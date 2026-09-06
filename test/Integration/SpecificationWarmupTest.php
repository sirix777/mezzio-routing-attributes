<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Integration;

use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\AttributeRouteProvider;
use Sirix\Mezzio\Routing\Attributes\Cache\NullRouteRegistrarCache;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheGenerator;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheLoader;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheStorage;
use Sirix\Mezzio\Routing\Attributes\Command\WarmRouteCacheCommand;
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
use Sirix\Mezzio\Routing\Contracts\Exception\InvalidMiddlewareSpecificationException;
use Sirix\Mezzio\Routing\Contracts\MiddlewareFactoryInterface;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;
use SirixTest\Mezzio\Routing\Attributes\Integration\Fixture\FactoryModifier;
use SirixTest\Mezzio\Routing\Attributes\Integration\Fixture\FactoryModifierHandler;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\RecordingRouteCollector;
use Symfony\Component\Console\Tester\CommandTester;

use function file_get_contents;
use function is_file;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class SpecificationWarmupTest extends TestCase
{
    public function testCachedFactoryAliasResolvesOnlyOnRequest(): void
    {
        $file      = sys_get_temp_dir() . '/specification-alias-' . uniqid('', true) . '.php';
        $extractor = new AttributeRouteExtractor(
            new ClassEligibilityValidator(false),
            new RouteAttributeReader(),
            new RouteDefinitionBuilder(new RouteAttributeReader(), new MethodSignatureValidator(), new RouteDataNormalizer())
        );
        $cache  = new CompiledRouteRegistrarCache($file, new RouteCacheGenerator(), new RouteCacheStorage(), new RouteCacheLoader());
        $config = RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes' => [FactoryModifierHandler::class],
            ],
        ]);
        $warmer     = new RouteCacheWarmer($config, $extractor, new DuplicateRouteResolver('throw'), new NullDiscoveredClassesResolver(), $cache);
        $container  = $this->createMock(ContainerInterface::class);
        $factory    = $this->createMock(MiddlewareFactoryInterface::class);
        $middleware = $this->createMock(MiddlewareInterface::class);
        $response   = new Response(status: 203);
        $middleware->expects(self::once())->method('process')->willReturn($response);
        $resolved = false;
        $container->expects(self::once())->method('get')->with('factory.alias')->willReturnCallback(
            static function() use ($factory, &$resolved): MiddlewareFactoryInterface {
                $resolved = true;

                return $factory;
            }
        );
        $factory->expects(self::once())->method('create')->with(
            $container,
            self::callback(static fn (MiddlewareSpecification $specification): bool => 'factory.alias' === $specification->factory)
        )->willReturn($middleware);

        try {
            FactoryModifier::$factory = 'factory.alias';
            self::assertTrue($warmer->warm());
            $collector = new RecordingRouteCollector();
            self::assertTrue($cache->registerRoutes($collector, new MiddlewarePipelineFactory($container, new ServiceMiddlewareResolver())));
            self::assertFalse($resolved);
            self::assertSame(
                $response,
                $collector->routes[0]->getMiddleware()->process(new ServerRequest(), $this->createMock(RequestHandlerInterface::class))
            );
        } finally {
            FactoryModifier::$factory = null;
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    #[DataProvider('invalidFactories')]
    public function testInvalidModifierFailsBeforeReplacingCache(?string $factory): void
    {
        $file      = sys_get_temp_dir() . '/specification-' . uniqid('', true) . '.php';
        $extractor = new AttributeRouteExtractor(
            new ClassEligibilityValidator(false),
            new RouteAttributeReader(),
            new RouteDefinitionBuilder(new RouteAttributeReader(), new MethodSignatureValidator(), new RouteDataNormalizer())
        );
        $cache     = new CompiledRouteRegistrarCache($file, new RouteCacheGenerator(), new RouteCacheStorage(), new RouteCacheLoader());
        $config    = RoutingAttributesConfig::fromRootConfig([
            'routing_attributes' => [
                'classes' => [FactoryModifierHandler::class],
            ],
        ]);
        $warmer    = new RouteCacheWarmer($config, $extractor, new DuplicateRouteResolver('throw'), new NullDiscoveredClassesResolver(), $cache);
        $command   = new CommandTester(new WarmRouteCacheCommand($warmer, $file));
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::never())->method('get');
        $pipeline = new MiddlewarePipelineFactory($container, new ServiceMiddlewareResolver());

        try {
            FactoryModifier::$factory = 'factory.alias';
            self::assertSame(0, $command->execute([]));
            $previous  = file_get_contents($file);
            $collector = new RecordingRouteCollector();
            self::assertTrue($cache->registerRoutes($collector, $pipeline));
            self::assertCount(1, $collector->routes);
            FactoryModifier::$factory = $factory;
            self::assertSame(1, $command->execute([]));
            self::assertStringContainsString(FactoryModifierHandler::class, $command->getDisplay());
            self::assertStringContainsString('factory', $command->getDisplay());
            self::assertSame($previous, file_get_contents($file));
            $provider = new AttributeRouteProvider(
                $extractor,
                [FactoryModifierHandler::class],
                new DuplicateRouteResolver('throw'),
                $pipeline,
                new NullRouteRegistrarCache()
            );
            $this->expectException(InvalidMiddlewareSpecificationException::class);
            $this->expectExceptionMessage(FactoryModifierHandler::class);
            $provider->registerRoutes(new RecordingRouteCollector());
        } finally {
            FactoryModifier::$factory = null;
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    #[DataProvider('invalidFactories')]
    public function testDirectPipelineRejectsInvalidFactoryWithoutResolvingServices(?string $factory): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::never())->method('get');
        $pipeline = new MiddlewarePipelineFactory($container, new ServiceMiddlewareResolver());
        $this->expectException(InvalidMiddlewareSpecificationException::class);
        $pipeline->createUncachedFromSignature('handler', 'handle', [new MiddlewareSpecification('middleware', $factory)]);
    }

    /** @return iterable<string, array{?string}> */
    public static function invalidFactories(): iterable
    {
        yield 'missing' => [null];

        yield 'empty' => [''];
    }
}
