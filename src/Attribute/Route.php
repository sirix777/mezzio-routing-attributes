<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Route
{
    /**
     * @param null|list<non-empty-string> $methods
     * @param null|list<non-empty-string> $middleware
     */
    public function __construct(
        public readonly string $path,
        public readonly ?array $methods = null,
        public readonly ?string $name = null,
        public readonly ?array $middleware = null
    ) {}
}
