<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Discovery;

interface DiscoveredClassesResolverInterface
{
    /**
     * @return list<non-empty-string>
     */
    public function resolve(): array;
}
