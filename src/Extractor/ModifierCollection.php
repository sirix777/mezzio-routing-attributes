<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Extractor;

use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;

/**
 * The accumulated result of route attribute modifiers for one reflection target.
 *
 * @internal
 */
final readonly class ModifierCollection
{
    /**
     * @param list<MiddlewareSpecification|non-empty-string>                    $middlewareServices
     * @param array<string, mixed>                                              $defaults
     * @param array<non-empty-string, MiddlewareSpecification|non-empty-string> $uniqueMiddlewareServices
     */
    public function __construct(public array $middlewareServices, public array $defaults, public array $uniqueMiddlewareServices) {}
}
