# Mezzio Routing Attributes

[![Latest Stable Version](http://poser.pugx.org/sirix/mezzio-routing-attributes/v)](https://packagist.org/packages/sirix/mezzio-routing-attributes)
[![Total Downloads](http://poser.pugx.org/sirix/mezzio-routing-attributes/downloads)](https://packagist.org/packages/sirix/mezzio-routing-attributes)
[![Latest Unstable Version](http://poser.pugx.org/sirix/mezzio-routing-attributes/v/unstable)](https://packagist.org/packages/sirix/mezzio-routing-attributes)
[![License](http://poser.pugx.org/sirix/mezzio-routing-attributes/license)](https://packagist.org/packages/sirix/mezzio-routing-attributes)
[![PHP Version Require](http://poser.pugx.org/sirix/mezzio-routing-attributes/require/php)](https://packagist.org/packages/sirix/mezzio-routing-attributes)

Attribute-based route registration for Mezzio applications.

> Warning: this package is not production-ready yet. Before `1.0.0`, backward compatibility is not guaranteed; public APIs and configuration may change between releases, including minor and patch releases.

## Status

This package provides:

- PHP 8 route attributes (`Route`, `Get`, `Post`, `Put`, `Patch`, `Delete`, `Any`)
- Extraction of routes from class-level and method-level attributes
- A route provider that registers extracted routes via `RouteCollectorInterface`
- Support for additional route middleware stacks via attribute `middleware: [...]`
- Class-level route prefixes inherited by method-level routes
- Class-level middleware inherited by method-level routes
- Automatic route registration via a `RouteCollector` delegator
- Hybrid operation with classic Mezzio routes defined outside attributes
- A `ConfigProvider` and default config structure

## Installation

```bash
composer require sirix/mezzio-routing-attributes
```

## Basic Usage

### 1. Register ConfigProvider

This is the manual registration variant. In a typical Mezzio application, the package `ConfigProvider` is discovered and registered automatically.

```php
$aggregator = new ConfigAggregator([
    // ...
    \Sirix\Mezzio\Routing\Attributes\ConfigProvider::class,
]);
```

### 2. Configure classes for scanning

```php
return [
    'routing_attributes' => [
        'classes' => [
            App\Handler\PingHandler::class,
        ],
        // "throw" (default) or "ignore"
        'duplicate_strategy' => 'throw',
        // Handler style: "psr15" (default) or "callable".
        // "callable" allows method-level routes on plain controller/action classes.
        'handlers' => [
            'mode' => 'psr15',
        ],
        // If true, overrides mezzio:routes:list (when mezzio/mezzio-tooling is installed).
        'override_mezzio_routes_list_command' => false,
        'route_list' => [
            // "upstream" (default) keeps classic routes identical to mezzio-tooling output.
            // "resolved" unwraps classic lazy-loaded routes to their underlying service name when possible.
            'classic_routes_middleware_display' => 'upstream',
        ],
        // Optional directory scanning (auto-discovery).
        'discovery' => [
            'enabled' => false,
            'paths' => [
                __DIR__ . '/../src/Handler',
            ],
            // "token" (default) or "psr4".
            'strategy' => 'token',
            'psr4' => [
                // Required when strategy = "psr4": base path => base namespace.
                'mappings' => [
                    __DIR__ . '/../src' => 'App\\',
                ],
                // If PSR-4 mapping cannot resolve a file, fallback to token parser.
                'fallback_to_token' => true,
            ],
            'class_map_cache' => [
                'enabled' => true,
                'file' => 'data/cache/mezzio-routing-attributes-classmap.php',
                // If true, cache is invalidated when discovered source file mtimes change.
                'validate' => true,
                // "ignore" (default) or "throw" when class map cache write fails.
                'write_fail_strategy' => 'ignore',
            ],
        ],
        'cache' => [
            // If true, extracted route definitions are loaded from/stored to a PHP file via require.
            'enabled' => false,
            'file' => 'data/cache/mezzio-routing-attributes.php',
            // If true, stale/invalid cache metadata throws instead of silent rebuild.
            'strict' => false,
            // "ignore" (default) or "throw" when route cache write fails.
            'write_fail_strategy' => 'ignore',
        ],
    ],
];
```

### 3. Add attributes

Route attributes can be placed either on the handler method or on the handler class. Both variants are supported.

Method-level attribute:

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

final class PingHandler implements RequestHandlerInterface
{
    #[Get('/ping', name: 'ping')]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new \RuntimeException('Not implemented in README example.');
    }
}
```

Class-level attribute:

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/ping', name: 'ping')]
final class PingHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new \RuntimeException('Not implemented in README example.');
    }
}
```

### 3.1 Route Parameters and Syntax

Route path syntax is not normalized by this package. Parameters, placeholders, optional segments, and inline requirements depend on the configured Mezzio router implementation.

In practice, route paths used in attributes should follow the same syntax rules as in Mezzio itself for your selected router (`mezzio/mezzio-fastroute`, `sirix/mezzio-radixrouter`, etc.).

### 3.2 Handler Modes (`psr15` vs `callable`)

The package supports two handler modes:

- `psr15` (default): strict Mezzio/PSR-15 style.
- `callable`: allows method-level routes on plain controller/action classes.

Configuration:

```php
return [
    'routing_attributes' => [
        'handlers' => [
            // "psr15" (default) or "callable"
            'mode' => 'psr15',
        ],
    ],
];
```

Rule that is always the same (both modes):

- Class-level route attributes (attribute placed on class) require the class to implement
  `MiddlewareInterface` or `RequestHandlerInterface`.

Where modes actually differ:

| Mode | Method-level attributes (`#[Get]` on method) |
|---|---|
| `psr15` | Class must implement `MiddlewareInterface` or `RequestHandlerInterface` |
| `callable` | Any class is allowed if target method is public and returns `ResponseInterface` |

Hybrid behavior in `callable` mode:

- PSR-15 handlers keep working exactly as before.
- Plain controller/action classes are additionally allowed for method-level routes.
- This means you can mix both styles in one project.

Service resolution in `callable` mode:

- Route handler class is resolved via container only.
- If service is missing in container, the application fails during bootstrap.
- Register every handler/action class in your container (`invokables`, `factories`, autowiring).

Example (plain controller/action class in `callable` mode):

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

final class ReportController
{
    #[Get('/reports/export', name: 'reports.export')]
    public function export(ServerRequestInterface $request): ResponseInterface
    {
        throw new \RuntimeException('Implement export response.');
    }
}
```

Laminas ServiceManager example:

```php
public function getDependencies(): array
{
    return [
        'invokables' => [
            App\Action\PingAction::class => App\Action\PingAction::class,
        ],
    ];
}
```

### 3.3 Hybrid Route Definitions

This package does not replace Mezzio's regular route configuration model.

- Attribute-defined routes and classic routes from `config/routes.php` can be used together in the same application.
- Both variants are registered into the same Mezzio routing table.
- You may even reuse the same handler class in both styles, as long as route names and `path + method` combinations do not conflict.

Typical hybrid setup:

- keep existing routes in `config/routes.php`
- introduce attribute routes gradually for new handlers or modules
- optionally enable `override_mezzio_routes_list_command=true` if you want the package CLI formatter to enhance `mezzio:routes:list`

### 4. Optional: Class-level prefix

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;
use Sirix\Mezzio\Routing\Attributes\Attribute\Route;

#[Route('/api')]
final class PingHandler implements RequestHandlerInterface
{
    #[Get('/ping', name: 'ping')]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new \RuntimeException('Not implemented in README example.');
    }
}
```

This registers `GET /api/ping`.

### 5. Optional: Route middleware stack

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

final class ExportHandler implements RequestHandlerInterface
{
    #[Get('/excel', name: 'excel.download', middleware: [
        App\Middleware\AuditMiddleware::class,
        App\Middleware\PackageVersionHeaderMiddleware::class,
    ])]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new \RuntimeException('This class is only used as route metadata anchor.');
    }
}
```

The registered pipeline is:

- `App\Middleware\AuditMiddleware`
- `App\Middleware\PackageVersionHeaderMiddleware`
- `ExportHandler::handle()`

Method-level route handlers are invoked as terminal handlers. They receive only
`ServerRequestInterface` and must return `ResponseInterface`.

### 6. Optional: CLI route listing

If `laminas/laminas-cli` is installed, the package registers:

```bash
vendor/bin/laminas routing-attributes:routes:list
```

This command prints attribute-defined middleware pipelines in a human-readable
form, for example:

- `App\Middleware\AuditMiddleware -> App\Handler\ExportHandler::download`

By default, the standard `mezzio:routes:list` command is left untouched.
If `mezzio/mezzio-tooling` is not installed, the package additionally
registers `mezzio:routes:list` as an alias to `routing-attributes:routes:list`.

For plain Symfony Console setups (without `laminas/laminas-cli`), this package
does not auto-register commands. Register `ListRoutesCommand` manually in your
console application bootstrap.

To override the standard command with the attribute-aware implementation, set:

```php
return [
    'routing_attributes' => [
        'override_mezzio_routes_list_command' => true,
    ],
];
```

For the override to work, both config providers must be registered and
`override_mezzio_routes_list_command` must be set to `true`.

When override is enabled, the command still prints the full routing table, not
only attribute-defined routes. Attribute-defined routes always use the
package's enhanced middleware display. Classic routes default to upstream
`mezzio-tooling` output, but can optionally display the resolved underlying
service name via `routing_attributes.route_list.classic_routes_middleware_display`.

## Configuration Impact

| Config Key | What It Changes | Runtime Impact | Production Recommendation |
|---|---|---|---|
| `routing_attributes.classes` | Explicit class list for attribute extraction | Fastest and deterministic | Use for critical routes where explicitness is preferred |
| `routing_attributes.duplicate_strategy` (`throw\|ignore`) | Behavior on duplicate attribute routes | No measurable hot-path cost | Keep `throw` |
| `routing_attributes.handlers.mode` (`psr15\|callable`) | Controls whether method-level routes require PSR-15 classes | No measurable hot-path cost | Keep `psr15`; use `callable` only when you need controller/action-style handlers |
| `routing_attributes.discovery.enabled` | Enables filesystem class discovery | More startup work than manual list | Enable only if you need auto-discovery |
| `routing_attributes.discovery.paths` | Directories scanned for routable classes | Wider paths = more scan cost | Keep path set narrow |
| `routing_attributes.discovery.strategy` (`token\|psr4`) | Controls how FQCN is resolved from discovered files | `psr4` can be faster on strict PSR-4 layouts; `token` is safest | Keep `token` unless layout is strictly PSR-4 and benchmarked |
| `routing_attributes.discovery.psr4.mappings` | Path-to-namespace map for `psr4` strategy | Mapping quality directly affects hit ratio | Keep mappings minimal and exact |
| `routing_attributes.discovery.psr4.fallback_to_token` | Fallback to token parsing when PSR-4 resolution fails | Better compatibility, slightly more work on misses | Keep `true` unless you want strict PSR-4-only discovery |
| `routing_attributes.discovery.class_map_cache.enabled` | Enables require-based discovery classmap cache | Major startup reduction after warmup | Keep `true` |
| `routing_attributes.discovery.class_map_cache.validate` | Validates discovery cache against filesystem changes | Adds small filesystem check overhead | `true` in prod unless deploy process handles cache rebuild |
| `routing_attributes.discovery.class_map_cache.write_fail_strategy` (`ignore\|throw`) | What happens if classmap cache cannot be written | Affects failure mode only | `ignore` for resilient runtime, `throw` for strict environments |
| `routing_attributes.cache.enabled` | Enables require-based route definition cache | Biggest startup optimization | Keep `true` |
| `routing_attributes.cache.file` | Route cache file location | No direct logic cost | Put in writable persistent cache dir |
| `routing_attributes.cache.strict` | Throws on stale/invalid cache metadata | Affects failure mode only | `false` unless strict fail-fast is required |
| `routing_attributes.cache.write_fail_strategy` (`ignore\|throw`) | What happens if route cache cannot be written | Affects failure mode only | `ignore` for resilient runtime, `throw` for strict environments |
| `routing_attributes.override_mezzio_routes_list_command` | Replaces `mezzio:routes:list` with attribute-aware command | CLI-only, no HTTP runtime effect | Enable only if you want override behavior |
| `routing_attributes.route_list.classic_routes_middleware_display` (`upstream\|resolved`) | Controls how classic lazy-loaded routes are shown in CLI route listings | CLI-only, no HTTP runtime effect | Keep `upstream`; use `resolved` if you want service names instead of `LazyLoadingMiddleware` |

## Recommended Production Settings

```php
return [
    'routing_attributes' => [
        'classes' => [
            // Keep explicit classes when possible.
            App\Handler\PingHandler::class,
        ],
        'duplicate_strategy' => 'throw',
        'handlers' => [
            'mode' => 'psr15',
        ],
        'override_mezzio_routes_list_command' => false,
        'route_list' => [
            'classic_routes_middleware_display' => 'upstream',
        ],
        'discovery' => [
            // Enable only if you need automatic class discovery.
            'enabled' => false,
            'paths' => [],
            'strategy' => 'token',
            'psr4' => [
                'mappings' => [],
                'fallback_to_token' => true,
            ],
            'class_map_cache' => [
                'enabled' => true,
                'file' => 'data/cache/mezzio-routing-attributes-classmap.php',
                'validate' => true,
                'write_fail_strategy' => 'ignore',
            ],
        ],
        'cache' => [
            'enabled' => true,
            'file' => 'data/cache/mezzio-routing-attributes.php',
            'strict' => false,
            'write_fail_strategy' => 'ignore',
        ],
    ],
];
```

Profile summary:

- Primary performance lever is `cache.enabled=true`.
- If discovery is used, keep `class_map_cache.enabled=true`.
- `validate=false` is fastest for discovery but does not auto-detect source changes.
- `write_fail_strategy=ignore` is safer for uptime; `throw` is stricter for controlled environments.

## Advanced Performance Tuning

This package intentionally exposes enough knobs to tune startup behavior for different environments.
There is no single best configuration for all projects.

Main tuning levers:

- Discovery source: explicit `classes` vs filesystem `discovery`.
- Discovery strategy: `token` (safer default) vs `psr4` (layout-dependent optimization).
- Discovery cache validation: `class_map_cache.validate=true|false`.
- Route definition cache: `cache.enabled=true|false`.
- Failure strategy: `write_fail_strategy=ignore|throw`.

Practical tuning profiles:

- Conservative production:
  - `cache.enabled=true`
  - discovery only when needed
  - `class_map_cache.enabled=true`
  - `class_map_cache.validate=true`
  - `write_fail_strategy=ignore`
- Fast immutable deploys (cache warmed during deploy):
  - `cache.enabled=true`
  - `class_map_cache.enabled=true`
  - `class_map_cache.validate=false`
  - optional `discovery.strategy=psr4` if benchmark-proven in your app
- Small/simple apps:
  - compare `cache.enabled=true` vs `cache.enabled=false`
  - with very few routes, no-cache may be comparable or faster

Adaptive workflow recommendation:

1. Start from the conservative profile.
2. Run `composer benchmark` in your environment.
3. Change one knob at a time.
4. Keep the configuration that improves your own latency/startup profile.

## Discovery Strategies

- `token` (default): parses PHP tokens to extract class names. Most compatible mode.
- `psr4`: builds class names from `discovery.psr4.mappings` (`base path => base namespace`).

Use `psr4` only when your scanned directories strictly follow PSR-4 layout.

- If a file cannot be resolved via mapping and `fallback_to_token=true`, token parser is used for that file.
- If `fallback_to_token=false`, unresolved files are skipped.

## Framework Semantics Warning

`handlers.mode=callable` is intentionally more permissive than native Mezzio PSR-15 conventions.

- Mezzio's core design centers around PSR-15 middleware/request handlers.
- `callable` mode allows method-level routing on plain classes, which is closer to classic controller/action style.
- This is a conscious trade-off for teams that want that style, but it is not the strict framework-first approach.

Recommendation:

- Prefer `handlers.mode=psr15` if you want to stay aligned with Mezzio architecture.
- Use `handlers.mode=callable` only when you explicitly accept this architectural deviation.

## Supported Features

- Classes implementing `Psr\Http\Server\MiddlewareInterface` or `Psr\Http\Server\RequestHandlerInterface`.
- In `handlers.mode=callable`, method-level routes can target plain classes (not only PSR-15 types).
- Manual class list in `routing_attributes.classes`.
- Optional filesystem discovery via `routing_attributes.discovery.paths`.
- Optional PSR-4 discovery strategy (`routing_attributes.discovery.strategy = token|psr4`) with per-file token fallback.
- Class-level and method-level route attributes.
- Class-level route prefixes for method-level routes.
- Optional route middleware stacks in attributes (`middleware: [First::class, Second::class]`).
- Configurable handler mode for method-level routes (`routing_attributes.handlers.mode = psr15|callable`).
- Configurable duplicate strategy for attribute routes (`duplicate_strategy: throw|ignore`).
- Optional require-based route cache (`routing_attributes.cache`) for OPcache-friendly startup.
- Optional require-based class map cache for discovery (`routing_attributes.discovery.class_map_cache`).
- Optional CLI command override for `mezzio:routes:list` when `mezzio/mezzio-tooling` is installed.

## Notes

- If a class contains method-level route attributes, class-level `Route` attributes are treated as shared route metadata such as path prefix and middleware.
- If a class has no method-level route attributes, a class-level `Route` attribute registers the class itself as the route handler via `handle()`.
- In `handlers.mode=callable`, method-level routes may target plain classes, but those classes must still be container services.
- Cache behavior: if `routing_attributes.cache.enabled=true` and cache file exists, metadata (`format_version`, duplicate strategy, classes fingerprint) is validated first; valid cache is loaded, stale cache is rebuilt (or throws when `cache.strict=true`).
- Route cache payload hydration is fail-fast: if any cached route entry is malformed, the whole cache payload is treated as invalid (no partial route loading).
- Discovery cache behavior: if `routing_attributes.discovery.class_map_cache.enabled=true`, class map is loaded via `require`; with `validate=true`, file `mtime` changes trigger rebuild and route cache invalidation. With `validate=false`, cache load is fastest but source changes are not detected automatically.
- Discovery cache is also invalidated when discovery strategy options change (`strategy`, `psr4.mappings`, `psr4.fallback_to_token`).
- Cache write failures can be configured: `write_fail_strategy=ignore|throw` for both route cache and discovery class map cache. In `throw` mode exception messages include captured filesystem error reason.

## Cache Invalidation Matrix

### Route definition cache (`routing_attributes.cache`)

| Situation | `cache.strict=false` | `cache.strict=true` |
|---|---|---|
| Cache file missing | Rebuild from extraction | Rebuild from extraction |
| Cache meta mismatch (`format_version`, strategy, fingerprint) | Treat as stale, rebuild | Throw `InvalidConfigurationException` |
| Malformed cache envelope/payload | Treat as invalid, rebuild | Throw `InvalidConfigurationException` |
| Any malformed route entry in payload | Treat whole payload as invalid, rebuild | Throw `InvalidConfigurationException` |
| Cache write failure (`write_fail_strategy=ignore`) | Continue without persisted cache | Continue without persisted cache |
| Cache write failure (`write_fail_strategy=throw`) | Throw with filesystem reason | Throw with filesystem reason |

### Discovery class map cache (`routing_attributes.discovery.class_map_cache`)

| Situation | `validate=false` | `validate=true` |
|---|---|---|
| Cache file missing | Re-scan filesystem and rebuild class map | Re-scan filesystem and rebuild class map |
| Paths mismatch / malformed payload | Re-scan filesystem and rebuild class map | Re-scan filesystem and rebuild class map |
| Discovery options changed (`strategy`, `psr4` mapping, fallback flag) | Re-scan filesystem and rebuild class map | Re-scan filesystem and rebuild class map |
| File inventory change (add/remove/rename/mtime) | Not checked | Cache invalidated, re-scan and rebuild |
| Cache write failure (`write_fail_strategy=ignore`) | Continue without persisted class map | Continue without persisted class map |
| Cache write failure (`write_fail_strategy=throw`) | Throw with filesystem reason | Throw with filesystem reason |

## Validation Timing

Validation is intentionally split into two layers.

Extraction-time validation (primary schema checks):

- class eligibility by mode (`psr15` / `callable`);
- method-level route target method visibility (`public`);
- method-level signature compatibility for request argument;
- declared return type compatibility with `ResponseInterface` (or no declared return type).
- strict type handling:
  - union return types must be fully `ResponseInterface`-compatible;
  - intersection request parameter types must include only `ServerRequestInterface`-compatible constraints.

Runtime validation (defense-in-depth during pipeline construction/invocation):

- container-resolved service type checks for middleware/handler behavior;
- target method existence and visibility checks before invocation;
- terminal method runtime return value check (`ResponseInterface` instance).

## Troubleshooting

- Error: service not found (`Unable to resolve service ...`): register the handler/action in container (`invokable`, `factory`, autowiring).
- Error: cache write failed: check directory permissions/path and use `write_fail_strategy=throw` to fail fast with exact filesystem reason.
- Route not updated after code changes: clear route/discovery cache files or keep discovery `validate=true`.
- Route missing after cache load: malformed cache payload now invalidates whole cache and triggers rebuild; in strict mode it throws.

## Performance Benchmark

Run:

```bash
composer benchmark
```

This generates:

- `benchmarks/report.json` (machine-readable report)
- console markdown table (can be redirected to `benchmarks/report.md`)

Scenarios include:

- warm route cache hit (manual class list)
- manual class list with route cache disabled
- cold route cache rebuild
- warm cache hit with discovery `validate=true`
- warm cache hit with discovery `validate=false`
- warm cache hit with discovery `strategy=psr4` and `validate=true`
- warm cache hit with discovery `strategy=psr4` and `validate=false`

Optional baseline comparison uses `benchmarks/baseline.json` and reports whether
warm cache-hit median regression stays within the `<= 5%` budget.

## Latest Benchmark Snapshot

Measured locally with:

- PHP `8.2.30`
- `100` iterations per scenario
- Command: `composer benchmark`

| Scenario | median ms | avg ms |
|---|---:|---:|
| `no_cache_manual` | `0.0060` | `0.0074` |
| `warm_cache_hit_manual` | `0.0097` | `0.0126` |
| `warm_cache_hit_discovery_validate_false` | `0.0176` | `0.0196` |
| `warm_cache_hit_discovery_psr4_validate_false` | `0.0181` | `0.0201` |
| `cold_cache_rebuild_manual` | `0.0192` | `0.0236` |
| `warm_cache_hit_discovery_psr4_validate_true` | `0.0248` | `0.0260` |
| `warm_cache_hit_discovery_validate_true` | `0.0253` | `0.0271` |

Notes:

- These numbers are not a universal or exact performance rating. Treat them as relative comparisons between package modes in the same environment.
- Absolute values depend on machine/filesystem/OPcache state.
- With a small number of routes, `no_cache` mode can be faster in practice. This depends on many factors (runtime, filesystem, OPcache, deployment model, route count, container behavior).
- You should benchmark in your own environment and choose the mode based on your workload. The benchmark in this package does not aim to fully model or optimize for all such factors.
- Use `benchmarks/baseline.json` + CI benchmark artifact to track regressions in your environment.

## License

MIT
