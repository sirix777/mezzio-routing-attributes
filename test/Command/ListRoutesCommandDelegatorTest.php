<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Command;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Command\ListRoutesCommand;
use Sirix\Mezzio\Routing\Attributes\Command\ListRoutesCommandDelegator;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\InMemoryContainer;
use stdClass;

final class ListRoutesCommandDelegatorTest extends TestCase
{
    public function testReturnsOriginalCommandWhenOverrideDisabled(): void
    {
        $original = new stdClass();
        $container = new InMemoryContainer([
            'config' => [
                'routing_attributes' => [
                    'override_mezzio_routes_list_command' => false,
                ],
            ],
        ]);

        $result = (new ListRoutesCommandDelegator())(
            $container,
            'Mezzio\Tooling\Routes\ListRoutesCommand',
            static fn (): object => $original
        );

        self::assertSame($original, $result);
    }

    public function testReturnsAttributeAwareCommandWhenOverrideEnabled(): void
    {
        $original = new stdClass();
        $replacement = new stdClass();
        $container = new InMemoryContainer([
            'config' => [
                'routing_attributes' => [
                    'override_mezzio_routes_list_command' => true,
                ],
            ],
            ListRoutesCommand::class => $replacement,
        ]);

        $result = (new ListRoutesCommandDelegator())(
            $container,
            'Mezzio\Tooling\Routes\ListRoutesCommand',
            static fn (): object => $original
        );

        self::assertSame($replacement, $result);
    }
}
