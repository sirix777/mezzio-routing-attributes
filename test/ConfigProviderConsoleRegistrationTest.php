<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes;

use Mezzio\Tooling\Routes\ConfigLoaderInterface;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Command\ListRoutesCommand;
use Sirix\Mezzio\Routing\Attributes\Command\ListRoutesCommandDelegator;
use Sirix\Mezzio\Routing\Attributes\Command\ListRoutesCommandFactory;
use Sirix\Mezzio\Routing\Attributes\ConfigProvider;
use Symfony\Component\Console\Command\Command;

use function class_exists;
use function interface_exists;
use function sprintf;

final class ConfigProviderConsoleRegistrationTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testDoesNotRegisterConsoleCommandWhenToolingClassesMissing(): void
    {
        if (
            class_exists(Command::class)
            && interface_exists(ConfigLoaderInterface::class)
            && class_exists(\Mezzio\Tooling\Routes\ListRoutesCommand::class)
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
                ListRoutesCommand::class,
                $config['laminas-cli']['commands']['mezzio:routes:list']
            );
            self::assertSame(ListRoutesCommandFactory::class, $dependencies['factories'][ListRoutesCommand::class]);
        } else {
            self::assertArrayNotHasKey('laminas-cli', $config);
            self::assertArrayNotHasKey(ListRoutesCommand::class, $dependencies['factories']);
        }

        self::assertArrayNotHasKey(\Mezzio\Tooling\Routes\ListRoutesCommand::class, $dependencies['delegators']);
    }

    #[RunInSeparateProcess]
    public function testRegistersConsoleCommandWhenToolingClassesAreAvailable(): void
    {
        $this->defineRuntimeClass('Symfony\Component\Console\Command', 'Command', 'abstract class Command {}');
        $this->defineRuntimeInterface(
            'Mezzio\Tooling\Routes',
            'ConfigLoaderInterface',
            'interface ConfigLoaderInterface { public function load(): void; }'
        );
        $this->defineRuntimeClass('Mezzio\Tooling\Routes', 'ListRoutesCommand', 'class ListRoutesCommand {}');

        $provider = new ConfigProvider();
        $config = $provider();
        $dependencies = $provider->getDependencies();

        self::assertArrayHasKey('laminas-cli', $config);
        self::assertSame(
            ListRoutesCommand::class,
            $config['laminas-cli']['commands']['routing-attributes:routes:list']
        );
        self::assertArrayNotHasKey('mezzio:routes:list', $config['laminas-cli']['commands']);
        self::assertSame(ListRoutesCommandFactory::class, $dependencies['factories'][ListRoutesCommand::class]);
        self::assertSame(
            [ListRoutesCommandDelegator::class],
            $dependencies['delegators'][\Mezzio\Tooling\Routes\ListRoutesCommand::class]
        );
    }

    private function defineRuntimeClass(string $namespace, string $shortName, string $definition): void
    {
        $fqcn = $namespace . '\\' . $shortName;
        if (! class_exists($fqcn)) {
            eval(sprintf('namespace %s; %s', $namespace, $definition));
        }
    }

    private function defineRuntimeInterface(string $namespace, string $shortName, string $definition): void
    {
        $fqcn = $namespace . '\\' . $shortName;
        if (! interface_exists($fqcn)) {
            eval(sprintf('namespace %s; %s', $namespace, $definition));
        }
    }
}
