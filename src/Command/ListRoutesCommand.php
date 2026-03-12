<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Command;

use const JSON_THROW_ON_ERROR;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function json_encode;
use function strtolower;

final class ListRoutesCommand extends Command
{
    private const HELP = <<<'EOT'
        Prints the application's routing table.

        For routes registered by mezzio-routing-attributes, the middleware column
        shows the full attribute pipeline in a human-readable form.
        EOT;

    /** @var null|string Cannot be defined explicitly due to parent class */
    public static $defaultName = 'routing-attributes:routes:list';

    public function __construct(
        private readonly RouteTableProvider $routeTableProvider,
        private readonly RouteListFilter $routeListFilter = new RouteListFilter(),
        private readonly RouteListSorter $routeListSorter = new RouteListSorter(),
        private readonly RouteListFormatter $routeListFormatter = new RouteListFormatter()
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription("Print the application's routing table.");
        $this->setHelp(self::HELP);

        $this->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format.', 'table');
        $this->addOption('sort', null, InputOption::VALUE_REQUIRED, 'Sort by "name" or "path".', 'name');
        $this->addOption('has-middleware', null, InputOption::VALUE_REQUIRED, 'Filter by middleware.', false);
        $this->addOption('has-name', null, InputOption::VALUE_REQUIRED, 'Filter by route name.', false);
        $this->addOption('has-path', null, InputOption::VALUE_REQUIRED, 'Filter by route path.', false);
        $this->addOption('supports-method', null, InputOption::VALUE_REQUIRED, 'Filter by HTTP method.', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $routes = $this->routeTableProvider->getRoutes();
        if ([] === $routes) {
            $output->writeln("There are no routes in the application's routing table.");

            return self::FAILURE;
        }

        $routes = $this->routeListFilter->filter(
            $routes,
            $input->getOption('has-name'),
            $input->getOption('has-path'),
            $input->getOption('has-middleware'),
            $input->getOption('supports-method')
        );
        $routes = $this->routeListSorter->sort($routes, $input->getOption('sort'));
        $rows = $this->routeListFormatter->formatRows($routes);

        if ('json' === strtolower((string) $input->getOption('format'))) {
            $output->writeln(json_encode($rows, JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaderTitle('Routes')
            ->setHeaders(['Name', 'Path', 'Methods', 'Middleware'])
            ->setRows($rows)
        ;
        $table->render();

        return self::SUCCESS;
    }
}
