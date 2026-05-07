<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Discovery;

/**
 * @internal
 */
final readonly class NullDiscoveredClassesResolver implements DiscoveredClassesResolverInterface
{
    public function resolve(): array
    {
        return [];
    }
}
