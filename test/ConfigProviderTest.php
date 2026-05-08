<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes;

use Laminas\ServiceManager\Config;
use Laminas\ServiceManager\ServiceManager;
use Mezzio\Router\RouteCollector;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\AttributeRouteProvider;
use Sirix\Mezzio\Routing\Attributes\AttributeRouteProviderFactory;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteRegistrarCacheInterface;
use Sirix\Mezzio\Routing\Attributes\Command\ClearRouteCacheCommand;
use Sirix\Mezzio\Routing\Attributes\Command\ClearRouteCacheCommandFactory;
use Sirix\Mezzio\Routing\Attributes\Command\ConsoleRegistrationPolicy;
use Sirix\Mezzio\Routing\Attributes\Command\ListRoutesCommand;
use Sirix\Mezzio\Routing\Attributes\Command\ListRoutesCommandDelegator;
use Sirix\Mezzio\Routing\Attributes\Command\RouteMiddlewareDisplayResolver;
use Sirix\Mezzio\Routing\Attributes\Command\RouteMiddlewareDisplayResolverFactory;
use Sirix\Mezzio\Routing\Attributes\ConfigProvider;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveredClassesResolverInterface;
use Sirix\Mezzio\Routing\Attributes\DuplicateRouteResolver;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractor;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorFactory;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;
use Sirix\Mezzio\Routing\Attributes\Factory\CompiledRouteRegistrarCacheFactory;
use Sirix\Mezzio\Routing\Attributes\Factory\DiscoveryClassMapResolverFactory;
use Sirix\Mezzio\Routing\Attributes\Factory\DuplicateRouteResolverFactory;
use Sirix\Mezzio\Routing\Attributes\Factory\MiddlewarePipelineFactoryFactory;
use Sirix\Mezzio\Routing\Attributes\MiddlewarePipelineFactory;
use Sirix\Mezzio\Routing\Attributes\RouteCollectorDelegator;
use Sirix\Mezzio\Routing\Attributes\ServiceMiddlewareResolver;

class ConfigProviderTest extends TestCase
{
    private const TOOLING_LIST_ROUTES_COMMAND = \Mezzio\Tooling\Routes\ListRoutesCommand::class;

    public function testProviderReturnsExpectedConfiguration(): void
    {
        $provider = new ConfigProvider();
        $config = $provider();

        self::assertArrayHasKey('dependencies', $config);
        self::assertSame($provider->getDependencies(), $config['dependencies']);
    }

    public function testDependenciesContainFactoriesAndAliases(): void
    {
        $provider = new ConfigProvider();
        $dependencies = $provider->getDependencies();

        self::assertArrayHasKey('factories', $dependencies);
        self::assertArrayHasKey('aliases', $dependencies);
        self::assertArrayHasKey('delegators', $dependencies);
        self::assertSame(AttributeRouteProviderFactory::class, $dependencies['factories'][AttributeRouteProvider::class]);
        self::assertSame(AttributeRouteExtractorFactory::class, $dependencies['factories'][AttributeRouteExtractor::class]);
        self::assertSame(AttributeRouteExtractor::class, $dependencies['aliases'][AttributeRouteExtractorInterface::class]);
        self::assertSame(
            DiscoveryClassMapResolverFactory::class,
            $dependencies['factories'][DiscoveredClassesResolverInterface::class]
        );
        self::assertSame(
            MiddlewarePipelineFactoryFactory::class,
            $dependencies['factories'][MiddlewarePipelineFactory::class]
        );
        self::assertSame(
            DuplicateRouteResolverFactory::class,
            $dependencies['factories'][DuplicateRouteResolver::class]
        );
        self::assertSame(
            CompiledRouteRegistrarCacheFactory::class,
            $dependencies['factories'][RouteRegistrarCacheInterface::class]
        );
        self::assertSame(
            ServiceMiddlewareResolver::class,
            $dependencies['invokables'][ServiceMiddlewareResolver::class]
        );
        self::assertSame([RouteCollectorDelegator::class], $dependencies['delegators'][RouteCollector::class]);

        $consoleDependencies = (new ConfigProvider(new ConsoleRegistrationPolicy(true, false)))->getDependencies();
        self::assertSame(ClearRouteCacheCommandFactory::class, $consoleDependencies['factories'][ClearRouteCacheCommand::class]);
        self::assertSame(
            RouteMiddlewareDisplayResolverFactory::class,
            $consoleDependencies['factories'][RouteMiddlewareDisplayResolver::class]
        );
    }

    public function testDependenciesConfigureServiceManagerForSelfContainedFactories(): void
    {
        $serviceManager = new ServiceManager();
        (new Config((new ConfigProvider(new ConsoleRegistrationPolicy(true, false)))->getDependencies()))
            ->configureServiceManager($serviceManager)
        ;
        $serviceManager->setService('config', [
            'routing_attributes' => [
                'classes' => [],
            ],
        ]);

        self::assertInstanceOf(
            ServiceMiddlewareResolver::class,
            $serviceManager->get(ServiceMiddlewareResolver::class)
        );
        self::assertInstanceOf(
            MiddlewarePipelineFactory::class,
            $serviceManager->get(MiddlewarePipelineFactory::class)
        );
        self::assertInstanceOf(
            DuplicateRouteResolver::class,
            $serviceManager->get(DuplicateRouteResolver::class)
        );
        self::assertInstanceOf(
            RouteMiddlewareDisplayResolver::class,
            $serviceManager->get(RouteMiddlewareDisplayResolver::class)
        );
        self::assertInstanceOf(
            AttributeRouteProvider::class,
            $serviceManager->get(AttributeRouteProvider::class)
        );
    }

    public function testRegistersConsoleAliasWhenLaminasCliAvailableWithoutTooling(): void
    {
        $provider = new ConfigProvider(new ConsoleRegistrationPolicy(true, false));
        $config = $provider();
        $dependencies = $provider->getDependencies();

        self::assertArrayHasKey('laminas-cli', $config);
        self::assertSame(
            ListRoutesCommand::class,
            $config['laminas-cli']['commands']['mezzio:routes:list']
        );
        self::assertSame(
            ClearRouteCacheCommand::class,
            $config['laminas-cli']['commands']['routing-attributes:cache:clear']
        );
        self::assertArrayNotHasKey(self::TOOLING_LIST_ROUTES_COMMAND, $dependencies['delegators']);
    }

    public function testRegistersToolingDelegatorWhenToolingAvailable(): void
    {
        $provider = new ConfigProvider(new ConsoleRegistrationPolicy(true, true));
        $config = $provider();
        $dependencies = $provider->getDependencies();

        self::assertArrayHasKey('laminas-cli', $config);
        self::assertArrayNotHasKey('mezzio:routes:list', $config['laminas-cli']['commands']);
        self::assertSame(
            ClearRouteCacheCommand::class,
            $config['laminas-cli']['commands']['routing-attributes:cache:clear']
        );
        self::assertSame(
            [ListRoutesCommandDelegator::class],
            $dependencies['delegators'][self::TOOLING_LIST_ROUTES_COMMAND]
        );
    }
}
