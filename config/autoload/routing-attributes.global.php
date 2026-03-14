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
            // Optional require-based cache for discovered class list.
            'class_map_cache' => [
                'enabled' => false,
                'file' => 'data/cache/mezzio-routing-attributes-classmap.php',
                // If true, validates source inventory fingerprint and rebuilds cache when files change.
                'validate' => true,
            ],
        ],
        // Optional route extraction cache loaded via require (OPcache-friendly).
        'cache' => [
            'enabled' => false,
            // Path for cached route definitions.
            'file' => 'data/cache/mezzio-routing-attributes.php',
            // If true, invalid/stale cache causes exception instead of silent rebuild.
            'strict' => false,
            // "ignore" (default) or "throw" when route cache write fails.
            'write_fail_strategy' => 'ignore',
        ],
    ],
];
