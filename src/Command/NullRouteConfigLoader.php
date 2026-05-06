<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Command;

final readonly class NullRouteConfigLoader implements RouteConfigLoaderInterface
{
    public function load(): void {}
}
