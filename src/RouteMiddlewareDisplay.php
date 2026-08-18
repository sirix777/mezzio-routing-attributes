<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes;

use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;

use function array_map;
use function implode;

/** @internal */
final class RouteMiddlewareDisplay
{
    /**
     * @param list<MiddlewareSpecification|non-empty-string> $middlewareServices
     *
     * @return non-empty-string
     */
    public static function format(string $handlerService, string $handlerMethod, array $middlewareServices): string
    {
        $handlerDisplay = $handlerService . '::' . $handlerMethod;

        $middlewareDisplays = array_map(
            static fn (MiddlewareSpecification|string $middleware): string => $middleware instanceof MiddlewareSpecification
                ? $middleware->service . (null === $middleware->factory ? '' : ' [factory: ' . $middleware->factory . ']')
                : $middleware,
            $middlewareServices
        );

        return [] === $middlewareDisplays
            ? $handlerDisplay
            : implode(' -> ', [...$middlewareDisplays, $handlerDisplay]);
    }
}
