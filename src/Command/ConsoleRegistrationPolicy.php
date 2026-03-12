<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Command;

use Laminas\Cli\ApplicationFactory;
use Mezzio\Tooling\Routes\ConfigLoaderInterface;
use Mezzio\Tooling\Routes\ListRoutesCommand;
use Symfony\Component\Console\Command\Command;

use function class_exists;
use function interface_exists;

final readonly class ConsoleRegistrationPolicy
{
    public function __construct(private bool $laminasCliAvailable, private bool $toolingOverrideAvailable) {}

    public static function fromRuntime(): self
    {
        $laminasCliAvailable = class_exists(Command::class)
            && class_exists(ApplicationFactory::class);
        $toolingOverrideAvailable = interface_exists(ConfigLoaderInterface::class)
            && class_exists(ListRoutesCommand::class);

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
