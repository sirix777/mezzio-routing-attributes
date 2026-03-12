<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Extractor;

use Sirix\Mezzio\Routing\Attributes\RouteDefinition;

interface AttributeRouteExtractorInterface
{
    /**
     * @param list<string> $classes
     *
     * @return list<RouteDefinition>
     */
    public function extract(array $classes): array;
}
