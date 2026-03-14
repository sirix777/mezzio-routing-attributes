# Mezzio Routing Attributes

[![Latest Stable Version](http://poser.pugx.org/sirix/mezzio-routing-attributes/v)](https://packagist.org/packages/sirix/mezzio-routing-attributes)
[![Total Downloads](http://poser.pugx.org/sirix/mezzio-routing-attributes/downloads)](https://packagist.org/packages/sirix/mezzio-routing-attributes)
[![Latest Unstable Version](http://poser.pugx.org/sirix/mezzio-routing-attributes/v/unstable)](https://packagist.org/packages/sirix/mezzio-routing-attributes)
[![License](http://poser.pugx.org/sirix/mezzio-routing-attributes/license)](https://packagist.org/packages/sirix/mezzio-routing-attributes)
[![PHP Version Require](http://poser.pugx.org/sirix/mezzio-routing-attributes/require/php)](https://packagist.org/packages/sirix/mezzio-routing-attributes)

Attribute-based route registration for Mezzio applications.

> Warning: this package is not production-ready yet. Before `1.0.0`, backward compatibility is not guaranteed.

## Installation

```bash
composer require sirix/mezzio-routing-attributes
```

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

Removed and no longer supported:

- `routing_attributes.lazy_service_resolution`
- `routing_attributes.cache.mode`
- `routing_attributes.cache.backend`
- `routing_attributes.cache.strict`
- `routing_attributes.cache.write_fail_strategy`
- `routing_attributes.discovery.class_map_cache`

## Discovery Behavior

- If `discovery.enabled=false`, only explicit `classes` are used.
- If `discovery.enabled=true`, classes are discovered from `discovery.paths`.
- If compiled cache is enabled and cache file already exists, discovery is skipped on boot.

## Compiled Cache Behavior

- If `cache.enabled=true` and cache file exists, routes are registered from compiled cache.
- If cache file is missing or invalid, routes are extracted/discovered and cache file is rebuilt.
- Cache format is optimized for startup speed and keeps middleware pipeline resolution lazy per service.

## Cache Clear Command

Clear compiled cache file:

```bash
php vendor/bin/laminas routing-attributes:cache:clear
```

Override file path:

```bash
php vendor/bin/laminas routing-attributes:cache:clear --file=data/cache/custom-routes.php
```

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

## Benchmarks

Run:

```bash
composer benchmark
composer benchmark-threshold
```

Latest local run (`PHP 8.2.30`):

- `warm_cache_hit_manual`: `0.0059 ms` median, `2.0625 KB` median peak
- `no_cache_manual`: `0.0211 ms` median, `3.4922 KB` median peak
- `cold_cache_rebuild_manual`: `0.0922 ms` median, `6.1719 KB` median peak
- `warm_cache_hit_discovery_token`: `0.0128 ms` median, `3.3438 KB` median peak
- `warm_cache_hit_discovery_psr4`: `0.0124 ms` median, `3.3438 KB` median peak
- Threshold benchmark (`compiled`) showed cache-win from `10` routes onward.
- At `12800` routes: `50.4975 ms` (no-cache) vs `23.1091 ms` (compiled), speedup `54.24%`;
  peak memory `13213.25 KB` vs `9260.84 KB`.

## Troubleshooting

- Service not found: register handler/action class in container.
- Route changes are not visible: clear compiled cache with `routing-attributes:cache:clear`.
- In long-running workers (RoadRunner/Swoole), reload/restart workers after cache rebuild/clear.
- Invalid cache payload errors: delete cache file and warm it again.
