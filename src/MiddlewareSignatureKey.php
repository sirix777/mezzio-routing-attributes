<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes;

use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;

use function array_map;
use function serialize;

/** @internal */
final class MiddlewareSignatureKey
{
    /**
     * @param list<MiddlewareSpecification|non-empty-string> $middlewareServices
     */
    public static function for(string $handlerService, string $handlerMethod, array $middlewareServices): string
    {
        return serialize([
            'handlerService'     => $handlerService,
            'handlerMethod'      => $handlerMethod,
            'middlewareServices' => array_map(
                static fn (MiddlewareSpecification|string $middleware): array => $middleware instanceof MiddlewareSpecification
                    ? [
                        'specification',
                        $middleware->service,
                        $middleware->factory,
                        $middleware->arguments,
                    ]
                    : ['service', $middleware],
                $middlewareServices
            ),
        ]);
    }
}
