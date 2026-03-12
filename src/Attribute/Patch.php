<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Patch extends Route
{
    /**
     * @param null|list<non-empty-string> $middleware
     */
    public function __construct(string $path, ?string $name = null, ?array $middleware = null)
    {
        parent::__construct($path, ['PATCH'], $name, $middleware);
    }
}
