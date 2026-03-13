<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes;

use Mezzio\Router\RouteCollector;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\AttributeRouteProvider;
use Sirix\Mezzio\Routing\Attributes\AttributeRouteProviderFactory;
use Sirix\Mezzio\Routing\Attributes\Command\ConsoleRegistrationPolicy;
use Sirix\Mezzio\Routing\Attributes\Command\ListRoutesCommand;
use Sirix\Mezzio\Routing\Attributes\Command\ListRoutesCommandDelegator;
use Sirix\Mezzio\Routing\Attributes\ConfigProvider;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractor;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorFactory;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;
use Sirix\Mezzio\Routing\Attributes\RouteCollectorDelegator;

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
        self::assertSame([RouteCollectorDelegator::class], $dependencies['delegators'][RouteCollector::class]);
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
            [ListRoutesCommandDelegator::class],
            $dependencies['delegators'][self::TOOLING_LIST_ROUTES_COMMAND]
        );
    }
}
