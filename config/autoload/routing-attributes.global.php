<?php

declare(strict_types=1);

return [
    'routing_attributes' => [
        // List handler classes to scan for PHP attributes.
        'classes' => [],
        // Duplicate strategy: "throw" (default) or "ignore".
        'duplicate_strategy' => 'throw',
        // Handler mode: "psr15" (default) or "callable" for method-level controllers/actions.
        'handlers' => [
            'mode' => 'psr15',
        ],
        // If true, overrides mezzio:routes:list with attribute-aware implementation (when tooling is installed).
        'override_mezzio_routes_list_command' => false,
        'route_list' => [
            // "upstream" (default) shows classic routes exactly like mezzio-tooling.
            // "resolved" unwraps classic lazy-loaded routes to their underlying service name when possible.
            'classic_routes_middleware_display' => 'upstream',
        ],
        // Optional class discovery by scanning directories for routable classes.
        'discovery' => [
            'enabled' => false,
            // Directories to scan for classes implementing MiddlewareInterface/RequestHandlerInterface.
            'paths' => [],
            // Discovery strategy: "token" (default) or "psr4".
            'strategy' => 'token',
            'psr4' => [
                // Required when strategy is "psr4": base path => base namespace.
                'mappings' => [],
                // Fallback to token parser when PSR-4 mapping does not resolve a class.
                'fallback_to_token' => true,
            ],
        ],
        // Optional compiled route registrar cache.
        'cache' => [
            // Production-oriented default: keep enabled for warm-cache performance.
            'enabled' => true,
            // Path for compiled route definitions.
            'file' => 'data/cache/mezzio-routing-attributes.php',
        ],
    ],
];
