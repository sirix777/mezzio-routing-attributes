<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Command;

/**
 * @internal
 */
interface RouteConfigLoaderInterface
{
    public function load(): void;
}
