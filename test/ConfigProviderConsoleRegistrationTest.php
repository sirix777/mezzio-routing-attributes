<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Command\ClearRouteCacheCommand;
use Sirix\Mezzio\Routing\Attributes\Command\ClearRouteCacheCommandFactory;
use Sirix\Mezzio\Routing\Attributes\Command\ListRoutesCommand;
use Sirix\Mezzio\Routing\Attributes\Command\ListRoutesCommandDelegator;
use Sirix\Mezzio\Routing\Attributes\Command\ListRoutesCommandFactory;
use Sirix\Mezzio\Routing\Attributes\ConfigProvider;
use Symfony\Component\Console\Command\Command;

use function class_exists;
use function interface_exists;

final class ConfigProviderConsoleRegistrationTest extends TestCase
{
    /** @noRector StringClassNameToClassConstantRector */
    private const TOOLING_CONFIG_LOADER_INTERFACE = 'Mezzio\Tooling\Routes\ConfigLoaderInterface';

    /** @noRector StringClassNameToClassConstantRector */
    private const TOOLING_LIST_ROUTES_COMMAND = 'Mezzio\Tooling\Routes\ListRoutesCommand';

    #[RunInSeparateProcess]
    public function testDoesNotRegisterConsoleCommandWhenToolingClassesMissing(): void
    {
        if (
            class_exists(Command::class)
            && interface_exists(self::TOOLING_CONFIG_LOADER_INTERFACE)
            && class_exists(self::TOOLING_LIST_ROUTES_COMMAND)
        ) {
            self::markTestSkipped('Tooling classes are installed in this test environment.');
        }

        $provider = new ConfigProvider();
        $config = $provider();
        $dependencies = $provider->getDependencies();

        if (class_exists(Command::class)) {
            self::assertArrayHasKey('laminas-cli', $config);
            self::assertSame(
                ListRoutesCommand::class,
                $config['laminas-cli']['commands']['routing-attributes:routes:list']
            );
            self::assertSame(
                ClearRouteCacheCommand::class,
                $config['laminas-cli']['commands']['routing-attributes:cache:clear']
            );
            self::assertSame(
                ListRoutesCommand::class,
                $config['laminas-cli']['commands']['mezzio:routes:list']
            );
            self::assertSame(ListRoutesCommandFactory::class, $dependencies['factories'][ListRoutesCommand::class]);
            self::assertSame(ClearRouteCacheCommandFactory::class, $dependencies['factories'][ClearRouteCacheCommand::class]);
        } else {
            self::assertArrayNotHasKey('laminas-cli', $config);
            self::assertArrayNotHasKey(ListRoutesCommand::class, $dependencies['factories']);
            self::assertArrayNotHasKey(ClearRouteCacheCommand::class, $dependencies['factories']);
        }

        self::assertArrayNotHasKey(self::TOOLING_LIST_ROUTES_COMMAND, $dependencies['delegators']);
    }

    #[RunInSeparateProcess]
    public function testRegistersConsoleCommandWhenToolingClassesAreAvailable(): void
    {
        require_once __DIR__ . '/Fixture/Mezzio/Tooling/Routes/ConfigLoaderInterface.php';

        require_once __DIR__ . '/Fixture/Mezzio/Tooling/Routes/ListRoutesCommand.php';

        $provider = new ConfigProvider();
        $config = $provider();
        $dependencies = $provider->getDependencies();

        self::assertArrayHasKey('laminas-cli', $config);
        self::assertSame(
            ListRoutesCommand::class,
            $config['laminas-cli']['commands']['routing-attributes:routes:list']
        );
        self::assertSame(
            ClearRouteCacheCommand::class,
            $config['laminas-cli']['commands']['routing-attributes:cache:clear']
        );
        self::assertArrayNotHasKey('mezzio:routes:list', $config['laminas-cli']['commands']);
        self::assertSame(ListRoutesCommandFactory::class, $dependencies['factories'][ListRoutesCommand::class]);
        self::assertSame(ClearRouteCacheCommandFactory::class, $dependencies['factories'][ClearRouteCacheCommand::class]);
        self::assertSame(
            [ListRoutesCommandDelegator::class],
            $dependencies['delegators'][self::TOOLING_LIST_ROUTES_COMMAND]
        );
    }
}
