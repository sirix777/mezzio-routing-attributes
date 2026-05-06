<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Factory;

use Sirix\Mezzio\Routing\Attributes\DuplicateRouteResolver;

final class DuplicateRouteResolverFactory
{
    /**
     * @param 'ignore'|'throw' $strategy
     */
    public function __invoke(string $strategy): DuplicateRouteResolver
    {
        return new DuplicateRouteResolver($strategy);
    }
}
