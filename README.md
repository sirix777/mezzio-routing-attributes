# Mezzio Routing Attributes

[![Latest Stable Version](http://poser.pugx.org/sirix/mezzio-routing-attributes/v)](https://packagist.org/packages/sirix/mezzio-routing-attributes)
[![Total Downloads](http://poser.pugx.org/sirix/mezzio-routing-attributes/downloads)](https://packagist.org/packages/sirix/mezzio-routing-attributes)
[![Latest Unstable Version](http://poser.pugx.org/sirix/mezzio-routing-attributes/v/unstable)](https://packagist.org/packages/sirix/mezzio-routing-attributes)
[![License](http://poser.pugx.org/sirix/mezzio-routing-attributes/license)](https://packagist.org/packages/sirix/mezzio-routing-attributes)
[![PHP Version Require](http://poser.pugx.org/sirix/mezzio-routing-attributes/require/php)](https://packagist.org/packages/sirix/mezzio-routing-attributes)

Attribute-based route registration for Mezzio applications.

Stable `1.0` releases follow semantic versioning for the public API documented below.

## Installation

```bash
composer require sirix/mezzio-routing-attributes
```

Optional CLI command registration requires console packages:

```bash
composer require laminas/laminas-cli symfony/console
```

Install `mezzio/mezzio-tooling` only when you want integration with Mezzio's upstream `mezzio:routes:list` command.

## Status

This package provides:

- PHP 8 route attributes (`Route`, `Get`, `Post`, `Put`, `Patch`, `Delete`, `Any`)
- Class-level and method-level attribute extraction
- Route provider registration via `RouteCollectorInterface`
- Optional route middleware stacks in attributes (`middleware: [...]`)
- Optional class discovery from configured directories
- Compiled route cache artifact (`require`-based)
- CLI commands:
  - `routing-attributes:routes:list`
  - `routing-attributes:cache:clear`

## Stability and Public API

The stable public API for `1.x` is:

- Route attributes:
  - `Sirix\Mezzio\Routing\Attributes\Attribute\Route`
  - `Sirix\Mezzio\Routing\Attributes\Attribute\Get`
  - `Sirix\Mezzio\Routing\Attributes\Attribute\Post`
  - `Sirix\Mezzio\Routing\Attributes\Attribute\Put`
  - `Sirix\Mezzio\Routing\Attributes\Attribute\Patch`
  - `Sirix\Mezzio\Routing\Attributes\Attribute\Delete`
  - `Sirix\Mezzio\Routing\Attributes\Attribute\Any`
- Configuration keys under `routing_attributes`:
  - `classes`
  - `duplicate_strategy`
  - `handlers.mode`
  - `override_mezzio_routes_list_command`
  - `route_list.classic_routes_middleware_display`
  - `discovery.enabled`
  - `discovery.paths`
  - `discovery.strategy`
  - `discovery.psr4.mappings`
  - `discovery.psr4.fallback_to_token`
  - `cache.enabled`
  - `cache.file`
- Extension contract for custom route metadata attributes:
  - `Sirix\Mezzio\Routing\Contracts\RouteAttributeModifierInterface`
- Package integration entry point:
  - `Sirix\Mezzio\Routing\Attributes\ConfigProvider`
- CLI command names and documented options:
  - `routing-attributes:routes:list`
  - `routing-attributes:cache:clear`

All other source classes are implementation details unless this README documents them as an integration point. They may be final, internal, or changed in a minor release when needed to keep the documented API working.

`Route` is public because it is the generic route attribute and the base class for the package HTTP-method attributes. For custom route metadata, prefer `RouteAttributeModifierInterface`; extending `Route` in application code is not a supported extension point.

Recommended production mode:

- use an explicit `classes` list;
- enable compiled cache;
- clear/warm cache during deploy;
- restart long-running workers after route/cache changes.

## Configuration

Production default (performance-first):

```php
return [
    'routing_attributes' => [
        'classes' => [
            App\Handler\PingHandler::class,
        ],
        'duplicate_strategy' => 'throw', // throw|ignore
        'handlers' => [
            'mode' => 'psr15', // psr15|callable
        ],
        'override_mezzio_routes_list_command' => false,
        'route_list' => [
            'classic_routes_middleware_display' => 'upstream', // upstream|resolved
        ],
        'discovery' => [
            'enabled' => false,
            'paths' => [],
            'strategy' => 'token', // token|psr4
            'psr4' => [
                'mappings' => [],
                'fallback_to_token' => true,
            ],
        ],
        'cache' => [
            'enabled' => true,
            'file' => 'data/cache/mezzio-routing-attributes.php',
        ],
    ],
];
```

Supported `routing_attributes.cache` keys:

- `enabled` (`bool`)
- `file` (`non-empty string`, required when `enabled=true`)

The package registers its own factories through `ConfigProvider`; application handlers and middleware still need to be available in your container.

## Optional CLI Support

CLI command registration is enabled only when the optional console dependencies are installed.

```bash
composer require laminas/laminas-cli symfony/console
```

When `mezzio/mezzio-tooling` is available, the package can decorate the upstream routes list command. Without it, the package registers its own `mezzio:routes:list` alias when console support is available.

## Discovery Behavior

- If `discovery.enabled=false`, only explicit `classes` are used.
- If `discovery.enabled=true`, classes are discovered from `discovery.paths`.
- If compiled cache is enabled and cache file already exists, discovery is skipped on boot.
- Prefer discovery for development or cache warmup, not as the main production boot path.
- `strategy=token` parses PHP files without requiring PSR-4 path mappings.
- `strategy=psr4` resolves class names from configured `discovery.psr4.mappings`; when `fallback_to_token=true`, files that cannot be mapped are parsed with the token strategy.

## Compiled Cache Behavior

- If `cache.enabled=true` and cache file exists, routes are registered from compiled cache.
- If cache file is missing or invalid, routes are extracted/discovered and cache file is rebuilt.
- Cache writes are best-effort: write failures do not break application boot, but they leave the next boot on the non-compiled path.
- Cache format is optimized for startup speed and keeps middleware pipeline resolution lazy per service.
- Ensure the cache directory is writable by the process that warms/rebuilds routes.

## Cache Clear Command

Clear compiled cache file:

```bash
php vendor/bin/laminas routing-attributes:cache:clear
```

Override file path:

```bash
php vendor/bin/laminas routing-attributes:cache:clear --file=data/cache/custom-routes.php
```

In RoadRunner/Swoole-style runtimes, reload workers after clearing or rebuilding the cache.

## Upgrading from `0.1.x`

`1.0.0` stabilizes the current production-oriented configuration model. Review these changes if your application started on an older `0.1.x` release:

- Custom attribute modifiers now use `Sirix\Mezzio\Routing\Contracts\RouteAttributeModifierInterface` from `sirix/mezzio-routing-contracts`. Replace the old `Sirix\Mezzio\Routing\Attributes\Contract\RouteAttributeModifierInterface` namespace.
- Compiled route cache is configured with `routing_attributes.cache.enabled` and `routing_attributes.cache.file`. Legacy cache keys such as `mode`, `backend`, `strict`, and `write_fail_strategy` are no longer supported.
- Discovery class-map cache configuration was removed. Use compiled route cache plus explicit `classes` for production, and enable discovery mainly for development or cache warmup.
- Optional CLI integrations are optional dependencies. Install `laminas/laminas-cli` and `symfony/console` when you want package commands registered automatically, and install `mezzio/mezzio-tooling` only for upstream route-list integration.
- Cache writes are best-effort. A failed cache write does not stop application boot, but the next boot will run the non-compiled path until the cache file can be written.

## Basic Usage

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
        throw new \RuntimeException('Implement your response.');
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
        throw new \RuntimeException('Implement your response.');
    }
}
```

## Custom Attribute Modifiers

You can create route-related attributes in your own package by implementing
`Sirix\Mezzio\Routing\Contracts\RouteAttributeModifierInterface`.

Example custom attribute:

```php
namespace Acme\Routing\Attribute;

use Attribute;
use Sirix\Mezzio\Routing\Contracts\RouteAttributeModifierInterface;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class RequireTenant implements RouteAttributeModifierInterface
{
    public function __construct(private string $tenantHeader = 'x-tenant-id') {}

    public function getMiddleware(): array
    {
        return [Acme\Middleware\RequireTenantMiddleware::class];
    }

    public function getDefaults(): array
    {
        return ['tenant_header' => $this->tenantHeader];
    }
}
```

Usage with route attributes:

```php
use Acme\Routing\Attribute\RequireTenant;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[RequireTenant('x-tenant-id')]
final class OrdersHandler
{
    #[Get('/orders', name: 'orders.list')]
    #[RequireTenant('x-org-id')]
    public function index(mixed ...$args): mixed
    {
        // ...
    }
}
```

### Route Defaults and Placeholders

The `getDefaults()` method allows you to provide default values for route placeholders. This is useful when you have optional parameters in your route paths.

Example with optional parameter:

```php
use Attribute;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;
use Sirix\Mezzio\Routing\Contracts\RouteAttributeModifierInterface;

#[Attribute]
final readonly class DefaultFormat implements RouteAttributeModifierInterface
{
    public function __construct(private string $format = 'html') {}

    public function getMiddleware(): array
    {
        return [];
    }

    public function getDefaults(): array
    {
        return ['format' => $this->format];
    }
}

final class ExportHandler
{
    #[Get('/export/:format?')]
    #[DefaultFormat('json')]
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        // $request->getAttribute('format') will be 'json' if not provided in URL
    }
}
```

Notes:

- class-level and method-level modifiers are merged for method routes;
- method-level defaults override class-level defaults on the same key;
- middleware from modifiers is appended after middleware declared in `Route`/`Get` attributes.
- defaults are passed to the Mezzio `Route::setOptions()` and can be used by the underlying router (like FastRoute) to fill missing optional placeholders.

## Benchmarks

Run:

```bash
composer benchmark
composer benchmark-threshold
```

Run test coverage with PCOV:

```bash
composer coverage
```

The coverage command requires the `pcov` PHP extension and runs PHPUnit with `pcov.enabled=1` and `pcov.directory=src`.

Baseline run (`PHP 8.2.30`, refreshed on `2026-05-25`):

- Provider benchmark command: `php8.2 benchmarks/route-provider-benchmark.php`
- Threshold benchmark command: `php8.2 benchmarks/route-cache-threshold-benchmark.php`
- Fixture corpus: `test/Extractor/Fixture`
- Manual scenarios register `2` routes; discovery scenarios register `10` routes from the fixture corpus.
- `warm_cache_hit_manual`: `0.0018 ms` median, `2.0156 KB` median peak
- `no_cache_manual`: `0.0074 ms` median, `3.4453 KB` median peak
- `cold_cache_rebuild_manual`: `0.0351 ms` median, `5.9063 KB` median peak
- `warm_cache_hit_discovery_token`: `0.0106 ms` median, `7.8906 KB` median peak
- `warm_cache_hit_discovery_psr4`: `0.0106 ms` median, `7.8906 KB` median peak
- Threshold benchmark (`compiled`) showed cache-win from `10` routes onward.
- At `12800` routes: `21.9587 ms` (no-cache) vs `8.6249 ms` (compiled), speedup `60.72%`;
  peak memory `13213.21 KB` vs `9260.80 KB`.

These are microbenchmarks for route registration/cache paths, not end-to-end HTTP latency.

## Troubleshooting

- Service not found: register handler/action class in container.
- Route changes are not visible: clear compiled cache with `routing-attributes:cache:clear`.
- In long-running workers (RoadRunner/Swoole), reload/restart workers after cache rebuild/clear.
- Invalid cache payload errors: delete cache file and warm it again.
