<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Discovery\Fixture;

use SirixTest\Mezzio\Routing\Attributes\Discovery\Fixture\AbstractRoutes\Psr15\AbstractHandler;

final class PrivateConstructorHandler extends AbstractHandler
{
    private function __construct() {}

    public static function create(): self
    {
        return new self();
    }
}
