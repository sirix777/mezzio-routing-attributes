<?php

declare(strict_types=1);

namespace Mezzio\Tooling\Routes;

use function interface_exists;

if (! interface_exists(ConfigLoaderInterface::class)) {
    interface ConfigLoaderInterface
    {
        public function load(): void;
    }
}
