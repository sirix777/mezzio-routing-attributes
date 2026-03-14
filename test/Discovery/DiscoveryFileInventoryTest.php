<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Discovery;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveryFileInventory;

use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class DiscoveryFileInventoryTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/mezzio-routing-attributes-inventory-' . uniqid('', true);
        mkdir($this->tempDir, 0o775, true);
        mkdir($this->tempDir . '/Nested', 0o775, true);

        file_put_contents($this->tempDir . '/A.php', '<?php class A {}');
        file_put_contents($this->tempDir . '/Nested/B.php', '<?php class B {}');
        file_put_contents($this->tempDir . '/skip.txt', 'nope');
    }

    protected function tearDown(): void
    {
        @unlink($this->tempDir . '/A.php');
        @unlink($this->tempDir . '/Nested/B.php');
        @unlink($this->tempDir . '/skip.txt');
        if (is_dir($this->tempDir . '/Nested')) {
            rmdir($this->tempDir . '/Nested');
        }

        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    public function testCollectReturnsOnlyPhpFiles(): void
    {
        /** @var non-empty-string $path */
        $path = $this->tempDir;
        $inventory = new DiscoveryFileInventory([$path]);
        $result = $inventory->collect();
        $paths = [];
        foreach ($result as [$file]) {
            $paths[] = $file;
        }

        self::assertContains($this->tempDir . '/A.php', $paths);
        self::assertContains($this->tempDir . '/Nested/B.php', $paths);
        self::assertNotContains($this->tempDir . '/skip.txt', $paths);
    }
}
