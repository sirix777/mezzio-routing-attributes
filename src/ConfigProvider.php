<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes;

use Mezzio\Router\RouteCollector;
use Sirix\Mezzio\Routing\Attributes\Command\ClearRouteCacheCommand;
use Sirix\Mezzio\Routing\Attributes\Command\ClearRouteCacheCommandFactory;
use Sirix\Mezzio\Routing\Attributes\Command\ConsoleRegistrationPolicy;
use Sirix\Mezzio\Routing\Attributes\Command\ListRoutesCommand;
use Sirix\Mezzio\Routing\Attributes\Command\ListRoutesCommandDelegator;
use Sirix\Mezzio\Routing\Attributes\Command\ListRoutesCommandFactory;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractor;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorFactory;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;

final readonly class ConfigProvider
{
    /** @noRector StringClassNameToClassConstantRector */
    private const TOOLING_LIST_ROUTES_COMMAND = 'Mezzio\Tooling\Routes\ListRoutesCommand';

    public function __construct(private ?ConsoleRegistrationPolicy $consoleRegistrationPolicy = null) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        $consolePolicy = $this->consoleRegistrationPolicy();
        $config = [
            'dependencies' => $this->getDependencies(),
        ];

        if ($consolePolicy->canRegisterConsoleConfig()) {
            $config['laminas-cli'] = $this->getConsoleConfig(
                $consolePolicy->shouldRegisterMezzioRoutesListAlias()
            );
        }

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDependencies(): array
    {
        $consolePolicy = $this->consoleRegistrationPolicy();
        $dependencies = [
            'factories' => [
                AttributeRouteProvider::class => AttributeRouteProviderFactory::class,
                AttributeRouteExtractor::class => AttributeRouteExtractorFactory::class,
            ],
            'aliases' => [
                AttributeRouteExtractorInterface::class => AttributeRouteExtractor::class,
            ],
            'delegators' => [
                RouteCollector::class => [
                    RouteCollectorDelegator::class,
                ],
            ],
        ];

        if ($consolePolicy->canRegisterConsoleConfig()) {
            $dependencies['factories'][ListRoutesCommand::class] = ListRoutesCommandFactory::class;
            $dependencies['factories'][ClearRouteCacheCommand::class] = ClearRouteCacheCommandFactory::class;
            if ($consolePolicy->shouldRegisterToolingDelegator()) {
                $dependencies['delegators'][self::TOOLING_LIST_ROUTES_COMMAND] = [
                    ListRoutesCommandDelegator::class,
                ];
            }
        }

        return $dependencies;
    }

    /**
     * @return array{commands: array<string, class-string>}
     */
    public function getConsoleConfig(bool $registerMezzioAlias = false): array
    {
        $commands = [
            'routing-attributes:routes:list' => ListRoutesCommand::class,
            'routing-attributes:cache:clear' => ClearRouteCacheCommand::class,
        ];

        if ($registerMezzioAlias) {
            $commands['mezzio:routes:list'] = ListRoutesCommand::class;
        }

        return [
            'commands' => [
                ...$commands,
            ],
        ];
    }

    private function consoleRegistrationPolicy(): ConsoleRegistrationPolicy
    {
        return $this->consoleRegistrationPolicy
            ?? ConsoleRegistrationPolicy::fromRuntime();
    }
}
