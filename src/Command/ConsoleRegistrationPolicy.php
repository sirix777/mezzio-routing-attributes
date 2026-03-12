<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Command;

use Laminas\Cli\ApplicationFactory;
use Symfony\Component\Console\Command\Command;

use function class_exists;
use function interface_exists;

final readonly class ConsoleRegistrationPolicy
{
    private const TOOLING_CONFIG_LOADER_INTERFACE = 'Mezzio\Tooling\Routes\ConfigLoaderInterface';
    private const TOOLING_LIST_ROUTES_COMMAND = 'Mezzio\Tooling\Routes\ListRoutesCommand';

    public function __construct(private bool $laminasCliAvailable, private bool $toolingOverrideAvailable) {}

    public static function fromRuntime(): self
    {
        $laminasCliAvailable = class_exists(Command::class)
            && class_exists(ApplicationFactory::class);
        $toolingOverrideAvailable = interface_exists(self::TOOLING_CONFIG_LOADER_INTERFACE)
            && class_exists(self::TOOLING_LIST_ROUTES_COMMAND);

        return new self($laminasCliAvailable, $toolingOverrideAvailable);
    }

    public function canRegisterConsoleConfig(): bool
    {
        return $this->laminasCliAvailable;
    }

    public function shouldRegisterToolingDelegator(): bool
    {
        return $this->laminasCliAvailable
            && $this->toolingOverrideAvailable;
    }

    public function shouldRegisterMezzioRoutesListAlias(): bool
    {
        return $this->laminasCliAvailable
            && ! $this->toolingOverrideAvailable;
    }
}
