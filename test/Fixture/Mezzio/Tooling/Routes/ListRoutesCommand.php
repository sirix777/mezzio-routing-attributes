<?php

declare(strict_types=1);

namespace Mezzio\Tooling\Routes;

use function class_exists;

if (! class_exists(ListRoutesCommand::class)) {
    final class ListRoutesCommand {}
}
