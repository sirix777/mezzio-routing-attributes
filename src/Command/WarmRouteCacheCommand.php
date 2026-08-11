<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Command;

use Sirix\Mezzio\Routing\Attributes\RouteCacheWarmer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class WarmRouteCacheCommand extends Command
{
    private const HELP = <<<'EOT'
        Intentionally builds the configured compiled route cache artifact.

        Run this command during deployment after application configuration and code are in place.
        It resolves configured and discovered route classes directly; it does not boot the application
        or reuse an existing cache artifact.
        EOT;

    /** @var null|string Cannot be defined explicitly due to parent class */
    public static $defaultName = 'routing-attributes:cache:warmup';

    public function __construct(private readonly ?RouteCacheWarmer $warmer, private readonly ?string $configuredCacheFile)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Build the compiled route cache artifact.');
        $this->setHelp(self::HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (! $this->warmer instanceof RouteCacheWarmer || null === $this->configuredCacheFile) {
            $output->writeln('<error>Route cache is not configured.</error>');

            return self::FAILURE;
        }

        try {
            if (! $this->warmer->warm()) {
                $output->writeln('<error>Failed to write route cache file: ' . $this->configuredCacheFile . '</error>');

                return self::FAILURE;
            }
        } catch (Throwable $error) {
            $output->writeln('<error>Failed to warm route cache: ' . $error->getMessage() . '</error>');

            return self::FAILURE;
        }

        $output->writeln('<info>Route cache warmed: ' . $this->configuredCacheFile . '</info>');

        return self::SUCCESS;
    }
}
