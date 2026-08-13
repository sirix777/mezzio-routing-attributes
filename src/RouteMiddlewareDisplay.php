<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes;

use function implode;

/** @internal */
final class RouteMiddlewareDisplay
{
    /**
     * @param list<non-empty-string> $middlewareServices
     *
     * @return non-empty-string
     */
    public static function format(string $handlerService, string $handlerMethod, array $middlewareServices): string
    {
        $handlerDisplay = $handlerService . '::' . $handlerMethod;

        return [] === $middlewareServices
            ? $handlerDisplay
            : implode(' -> ', [...$middlewareServices, $handlerDisplay]);
    }
}
