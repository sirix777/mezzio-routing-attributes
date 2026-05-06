<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Command;

use Closure;

final readonly class ClosureRouteConfigLoader implements RouteConfigLoaderInterface
{
    /**
     * @param Closure():void $loadConfig
     */
    public function __construct(private Closure $loadConfig) {}

    public function load(): void
    {
        ($this->loadConfig)();
    }
}
