<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function is_file;
use function is_string;
use function unlink;

final class ClearRouteCacheCommand extends Command
{
    private const HELP = <<<'EOT'
        Deletes compiled route cache artifact.

        By default uses routing_attributes.cache.file from config.
        Use --file to override cache path for operational scenarios.
        EOT;

    /** @var null|string Cannot be defined explicitly due to parent class */
    public static $defaultName = 'routing-attributes:cache:clear';

    public function __construct(private readonly ?string $configuredCacheFile)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Delete compiled route cache artifact.');
        $this->setHelp(self::HELP);
        $this->addOption('file', null, InputOption::VALUE_REQUIRED, 'Override cache file path.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getOption('file');
        if (is_string($file) && '' !== $file) {
            return $this->clearFile($file, $output);
        }

        if (null === $this->configuredCacheFile || '' === $this->configuredCacheFile) {
            $output->writeln('<error>Route cache file is not configured.</error>');

            return self::FAILURE;
        }

        return $this->clearFile($this->configuredCacheFile, $output);
    }

    private function clearFile(string $file, OutputInterface $output): int
    {
        if (! is_file($file)) {
            $output->writeln('<info>Route cache file does not exist: ' . $file . '</info>');

            return self::SUCCESS;
        }

        if (! @unlink($file)) {
            $output->writeln('<error>Failed to delete route cache file: ' . $file . '</error>');

            return self::FAILURE;
        }

        $output->writeln('<info>Route cache file deleted: ' . $file . '</info>');

        return self::SUCCESS;
    }
}
