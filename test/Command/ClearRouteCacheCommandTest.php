<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Command;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Command\ClearRouteCacheCommand;
use Symfony\Component\Console\Tester\CommandTester;

use function file_put_contents;
use function is_file;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class ClearRouteCacheCommandTest extends TestCase
{
    /** @var list<string> */
    private array $cacheFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->cacheFiles as $cacheFile) {
            if (is_file($cacheFile)) {
                unlink($cacheFile);
            }
        }
    }

    public function testDeletesConfiguredCacheFile(): void
    {
        $cacheFile = sys_get_temp_dir() . '/routing-attributes-cache-clear-' . uniqid('', true) . '.php';
        $this->cacheFiles[] = $cacheFile;
        file_put_contents($cacheFile, '<?php return [];');

        $command = new ClearRouteCacheCommand($cacheFile);
        $tester = new CommandTester($command);

        self::assertSame(0, $tester->execute([]));
        self::assertFalse(is_file($cacheFile));
        self::assertStringContainsString('Route cache file deleted', $tester->getDisplay());
    }

    public function testReturnsSuccessWhenFileDoesNotExist(): void
    {
        $cacheFile = sys_get_temp_dir() . '/routing-attributes-cache-clear-missing-' . uniqid('', true) . '.php';
        $command = new ClearRouteCacheCommand($cacheFile);
        $tester = new CommandTester($command);

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('does not exist', $tester->getDisplay());
    }

    public function testAllowsFileOverrideOption(): void
    {
        $configuredFile = sys_get_temp_dir() . '/routing-attributes-cache-clear-configured-' . uniqid('', true) . '.php';
        $overrideFile = sys_get_temp_dir() . '/routing-attributes-cache-clear-override-' . uniqid('', true) . '.php';
        $this->cacheFiles[] = $configuredFile;
        $this->cacheFiles[] = $overrideFile;
        file_put_contents($configuredFile, '<?php return [];');
        file_put_contents($overrideFile, '<?php return [];');

        $command = new ClearRouteCacheCommand($configuredFile);
        $tester = new CommandTester($command);

        self::assertSame(0, $tester->execute(['--file' => $overrideFile]));
        self::assertTrue(is_file($configuredFile));
        self::assertFalse(is_file($overrideFile));
    }

    public function testFailsWhenCacheFileIsNotConfigured(): void
    {
        $command = new ClearRouteCacheCommand(null);
        $tester = new CommandTester($command);

        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString('not configured', $tester->getDisplay());
    }
}
