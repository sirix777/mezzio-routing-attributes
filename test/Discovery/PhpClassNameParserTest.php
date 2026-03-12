<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Discovery;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Discovery\PhpClassNameParser;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\PingHandler;

use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class PhpClassNameParserTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/mezzio-routing-attributes-parser-' . uniqid('', true);
        mkdir($this->tempDir, 0o775, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = scandir($this->tempDir);
            if (false !== $files) {
                foreach ($files as $file) {
                    if ('.' === $file) {
                        continue;
                    }

                    if ('..' === $file) {
                        continue;
                    }

                    @unlink($this->tempDir . '/' . $file);
                }
            }

            rmdir($this->tempDir);
        }
    }

    public function testParsesNamedClassFromFixture(): void
    {
        $result = (new PhpClassNameParser())->parse(__DIR__ . '/../Extractor/Fixture/PingHandler.php');

        self::assertContains(PingHandler::class, $result);
    }

    public function testIgnoresAnonymousClass(): void
    {
        $file = $this->tempDir . '/Anonymous.php';
        file_put_contents($file, <<<'PHP'
            <?php
            declare(strict_types=1);
            new class {};
            PHP);

        $result = (new PhpClassNameParser())->parse($file);

        self::assertSame([], $result);
    }

    public function testDoesNotTreatClassConstantFetchAsClassDeclaration(): void
    {
        $file = $this->tempDir . '/ClassConstantFetch.php';
        file_put_contents($file, <<<'PHP'
            <?php
            declare(strict_types=1);

            namespace App\Handler;

            use App\Middleware\PackageVersionHeaderMiddleware;
            use Psr\Http\Message\ResponseInterface;
            use Psr\Http\Message\ServerRequestInterface;
            use Psr\Http\Server\RequestHandlerInterface;
            use Sirix\Mezzio\Routing\Attributes\Attribute\Get;
            use Sirix\Mezzio\Routing\Attributes\Attribute\Route;

            #[Route('/api', middleware: [PackageVersionHeaderMiddleware::class])]
            class PingHandler implements RequestHandlerInterface
            {
                #[Get('/pong/:id?', name: 'pong', middleware: [PackageVersionHeaderMiddleware::class])]
                public function pong(ServerRequestInterface $request): ResponseInterface
                {
                    throw new \RuntimeException();
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    throw new \RuntimeException();
                }
            }
            PHP);

        $result = (new PhpClassNameParser())->parse($file);

        self::assertSame(['App\Handler\PingHandler'], $result);
    }
}
