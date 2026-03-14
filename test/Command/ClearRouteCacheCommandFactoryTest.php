<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Command;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Command\ClearRouteCacheCommand;
use Sirix\Mezzio\Routing\Attributes\Command\ClearRouteCacheCommandFactory;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\InMemoryContainer;

final class ClearRouteCacheCommandFactoryTest extends TestCase
{
    public function testCreatesCommandFromConfiguration(): void
    {
        $factory = new ClearRouteCacheCommandFactory();
        $container = new InMemoryContainer([
            'config' => [
                'routing_attributes' => [
                    'classes' => [],
                    'cache' => [
                        'enabled' => true,
                        'file' => '/tmp/mezzio-routing-attributes.php',
                    ],
                ],
            ],
        ]);

        $command = $factory($container);

        self::assertInstanceOf(ClearRouteCacheCommand::class, $command);
    }
}
