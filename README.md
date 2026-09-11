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
  - `routing-attributes:cache:warmup`

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
  - `cache.release`
- Extension contract for custom route metadata attributes:
  - `Sirix\Mezzio\Routing\Contracts\RouteAttributeModifierInterface`
- Package integration entry point:
  - `Sirix\Mezzio\Routing\Attributes\ConfigProvider`
- CLI command names and documented options:
  - `routing-attributes:routes:list`
  - `routing-attributes:cache:clear`
  - `routing-attributes:cache:warmup`

All other source classes are implementation details unless this README documents them as an integration point. They may be final, internal, or changed in a minor release when needed to keep the documented API working.

`Route` is public because it is the generic route attribute and the base class for the package HTTP-method attributes. For custom route metadata, prefer `RouteAttributeModifierInterface`; extending `Route` in application code is not a supported extension point.

Recommended production mode:

- use an explicit `classes` list;
- enable compiled cache;
- build the cache explicitly during deploy with `routing-attributes:cache:warmup`;
- reload long-running workers after the newly built artifact is active.

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
            // Set to a unique application build/release identifier in production.
            'release' => null,
        ],
    ],
];
```

Supported `routing_attributes.cache` keys:

- `enabled` (`bool`)
- `file` (`non-empty string`, required when `enabled=true`)
- `release` (`null|non-empty string`, optional): an application-controlled deployment/release identifier.

Load `Sirix\Mezzio\Routing\Attributes\ConfigProvider` in your application's configuration aggregator (accept automatic registration if offered by the installer). It registers the package's own factories; application handlers, middleware service IDs, and specification factory service IDs must still resolve through your PSR-11 container. A class listed in `routing_attributes.classes` is not automatically registered as a container service. Keep application classes autoloadable and configure their services/factories or aliases in the container.

## Optional CLI Support

CLI command registration is enabled only when the optional console dependencies are installed.

```bash
composer require laminas/laminas-cli symfony/console
```

When `mezzio/mezzio-tooling` is available, the package can decorate the upstream routes list command. Without it, the package registers its own `mezzio:routes:list` alias when console support is available.

With tooling installed, set `routing_attributes.override_mezzio_routes_list_command=true` to replace the upstream `mezzio:routes:list` display with this package's attribute-aware command. The default `false` keeps the upstream command; `routing-attributes:routes:list` remains available separately. A plain Symfony Console application needs manual command registration; automatic registration uses Laminas CLI.

```bash
php vendor/bin/laminas routing-attributes:routes:list --format=json --sort=path
php vendor/bin/laminas routing-attributes:routes:list --sort=name --has-name=orders.
php vendor/bin/laminas routing-attributes:routes:list --has-path=/orders --has-middleware=RequireTenant --supports-method=get
```

For this package's command (and its alias or enabled override), `--sort` accepts `name` (the default) or `path`. Name and path filters match literal, case-sensitive prefixes; middleware matches a literal, case-insensitive substring of the displayed middleware pipeline. All active filters combine with AND. The method filter is case-insensitive and includes routes allowing any method, provided they also satisfy the other filters. These semantics do not describe the unmodified upstream tooling command.

JSON output is an array of objects with string fields `name`, `path`, `methods`, and `middleware`. `methods` is comma-separated (for example, `"GET,POST"`); an ANY route retains `"methods": ""`, also shown as an empty table cell. This representation is preserved for existing JSON consumers; an empty methods string means all methods are allowed.

## Discovery Behavior

- If `discovery.enabled=false`, only explicit `classes` are used.
- If `discovery.enabled=true`, classes are discovered from `discovery.paths`.
- If compiled cache is enabled and its artifact is a usable regular file with matching format and fingerprint, discovery is skipped on boot.
- Prefer discovery for development or cache warmup, not as the main production boot path.
- In `handlers.mode=callable`, discovery includes plain classes only when they have a method-level route attribute; irrelevant plain classes are skipped. Explicit `classes` entries remain strict and must be PSR-15 handlers unless they define method routes in callable mode.
- Automatic discovery skips abstract classes in both modes and both strategies. Concrete subclasses can expose inherited attributed methods. Explicit `classes` may still name an abstract class when the container binds that service ID to a concrete implementation; classes with private constructors are also allowed when a factory supplies them.
- `strategy=token` parses PHP files without requiring PSR-4 path mappings.
- `strategy=psr4` resolves class names from configured `discovery.psr4.mappings`; when `fallback_to_token=true`, files that cannot be mapped are parsed with the token strategy.

## Compiled Cache Behavior

- Each artifact includes a format version and a fingerprint of the effective route-producing configuration and optional `cache.release` identifier. If either does not match, it is a cache miss.
- If `cache.enabled=true` and the artifact matches, routes are registered from compiled cache.
- If the file is missing, invalid, or stale, routes are extracted/discovered and the package attempts to rebuild it.
- Cache writes are best-effort: write failures do not break application boot, but they leave the next boot on the non-compiled path.
- Write failures are sent to `Psr\Log\LoggerInterface` only when both `psr/log` is installed and that service is registered in the application container.
- Cache format is optimized for startup speed and keeps middleware pipeline resolution lazy per service.
- The package rejects symlink cache targets and existing non-regular target files when writing. It does not manage cache-file ownership, `chmod`, ACLs, release paths, or runtime-vs-deploy policy.
- With compiled cache enabled, route defaults must be recursively scalar, `null`, or arrays. Closures, resources, objects, and recursive array structures are rejected before routes are registered. Without compiled cache, defaults remain unrestricted.

## Cache Operations

Build the artifact deliberately during deploy:

```bash
php vendor/bin/laminas routing-attributes:cache:warmup
```

The warmup command resolves configured and discovered route classes directly and does not reuse an existing artifact or boot your application. It requires `cache.enabled=true` and uses the configured `cache.file` path.

A successful warmup validates route structure and writes the artifact. It does not instantiate route handlers or middleware, resolve their specification factories, or execute arbitrary container factory logic. Verify service wiring and application behavior with HTTP tests as well as warmup; factory failures may otherwise appear only on the first request.

Set `cache.release` to a unique immutable build or release ID and change it for every deployment that can change route classes, attributes, middleware, or route modifiers. This package intentionally does not hash application source files at runtime: doing so would require rediscovery/reflection on every cache hit and still could not reliably model all autoloaded route dependencies. When `cache.release` is omitted, only the package format and effective routing configuration invalidate the artifact; use that omission only for development or single-user environments.

Clear a compiled cache file only when your deployment process owns that operation:

```bash
php vendor/bin/laminas routing-attributes:cache:clear
```

Override file path:

```bash
php vendor/bin/laminas routing-attributes:cache:clear --file=data/cache/custom-routes.php
```

`--file` bypasses the configured cache path and deletes the exact path supplied. Treat it as a deploy-only administrative override; never construct it from untrusted input.

Production deployments **must** use a release-specific, deploy-owned cache directory that the runtime user cannot write. Only the deploy user may create or remove the artifact; the web/runtime user needs read-only access to the completed artifact and its directory. Do not place it in upload or other shared-writable locations: a PHP cache artifact is executable code, and pathname checks cannot make a directory writable by an attacker safe from replacement races.

On POSIX systems, `tempnam()` creates the temporary artifact with mode `0600`, and the atomic rename preserves that mode. When deploy and runtime use different users, the application must explicitly grant the runtime user read access after warmup through its own group or ACL policy.

### Optional cache-failure logging with `sirix/monolog`

[`sirix/monolog`](https://packagist.org/packages/sirix/monolog) registers `Psr\Log\LoggerInterface` for its default logger service, so the cache package will use it automatically:

```bash
composer require sirix/monolog
```

Configure a real handler as described in the [`sirix/monolog` documentation](https://github.com/sirix777/sirix-monolog/blob/main/README.md); its default logger is a no-op. This package logs cache write failures at the `error` level with `operation`, `cache_file`, and filesystem-error context.

Recommended sequence: deploy the new release, run `routing-attributes:cache:warmup`, activate the release/artifact, then reload long-running workers. If a deployment must delete a cache file, do so only as the deploy user and immediately warm a replacement; avoid deleting an artifact still used by live workers.

In RoadRunner/Swoole-style runtimes, reload workers after a newly warmed artifact is active.

## Upgrading from `0.1.x`

`1.0.0` stabilizes the current production-oriented configuration model. Review these changes if your application started on an older `0.1.x` release:

- Custom attribute modifiers now use `Sirix\Mezzio\Routing\Contracts\RouteAttributeModifierInterface` from `sirix/mezzio-routing-contracts`. Replace the old `Sirix\Mezzio\Routing\Attributes\Contract\RouteAttributeModifierInterface` namespace.
- Compiled route cache is configured with `routing_attributes.cache.enabled` and `routing_attributes.cache.file`. Legacy cache keys such as `mode`, `backend`, `strict`, and `write_fail_strategy` are no longer supported.
- Discovery class-map cache configuration was removed. Use compiled route cache plus explicit `classes` for production, and enable discovery mainly for development or cache warmup.
- Optional CLI integrations are optional dependencies. Install `laminas/laminas-cli` and `symfony/console` when you want package commands registered automatically, and install `mezzio/mezzio-tooling` only for upstream route-list integration.
- Cache writes are best-effort at runtime. During deployment, run `routing-attributes:cache:warmup` and treat a non-zero exit as a deploy failure before activating the release.

## Basic Usage

Response examples use `Laminas\Diactoros\Response\JsonResponse`; install `laminas/laminas-diactoros` if it is not already available, or substitute your application's PSR-7 response implementation. Register each example handler as a container service and add it to `routing_attributes.classes` (or discovery). The two `PingHandler` examples below are alternatives.

Method-level attribute:

```php
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

final class PingHandler implements RequestHandlerInterface
{
    #[Get('/ping', name: 'ping')]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse(['ping' => 'pong']);
    }
}
```

### Callable Handler Methods

With `handlers.mode=callable`, a method route may accept the request alone or also receive the downstream handler. The invoker always supplies both arguments, so a declared second parameter (or a variadic parameter that captures it) must accept `RequestHandlerInterface`; untyped, `mixed`, `object`, and compatible union/intersection types are supported. Further declared parameters are allowed only when optional and receive their default values.

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

final class ReportAction
{
    #[Get('/report')]
    public function index(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }
}
```

Class-level attribute:

```php
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/ping', name: 'ping')]
final class PingHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse(['ping' => 'pong']);
    }
}
```

## Custom Attribute Modifiers

You can create route-related attributes in your own package by implementing
`Sirix\Mezzio\Routing\Contracts\RouteAttributeModifierInterface`.

Example custom attribute:

```php
namespace Acme\Routing\Attribute;

use Acme\Middleware\RequireTenantMiddleware;
use Attribute;
use Sirix\Mezzio\Routing\Contracts\RouteAttributeModifierInterface;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class RequireTenant implements RouteAttributeModifierInterface
{
    public function __construct(private string $tenantHeader = 'x-tenant-id') {}

    public function getMiddleware(): array
    {
        return [RequireTenantMiddleware::class];
    }

    public function getDefaults(): array
    {
        return ['tenant_header' => $this->tenantHeader];
    }
}
```

Usage with route attributes:

This plain `OrdersHandler` requires `routing_attributes.handlers.mode=callable`. Register `OrdersHandler::class` and `Acme\Middleware\RequireTenantMiddleware::class` as container services, and include the handler in `routing_attributes.classes` or discovery.

```php
use Acme\Routing\Attribute\RequireTenant;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[RequireTenant('x-tenant-id')]
final class OrdersHandler
{
    #[Get('/orders', name: 'orders.list')]
    #[RequireTenant('x-org-id')]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse(['orders' => []]);
    }
}
```

For example, the routing configuration for that handler is:

```php
return [
    'routing_attributes' => [
        'classes' => [OrdersHandler::class],
        'handlers' => ['mode' => 'callable'],
    ],
];
```

`RequireTenantMiddleware` is application-provided: the attribute only adds middleware and options, and does not itself validate a tenant or grant authorization. The method modifier's `tenant_header` option overrides the class modifier's value; propagation of options to a request depends on the router as described below.

### Repeatable Aggregating Modifiers

`Sirix\Mezzio\Routing\Contracts\AggregatingRouteAttributeModifierInterface` is an opt-in extension for a repeatable modifier whose defaults must accumulate and whose middleware must run once. It extends `RouteAttributeModifierInterface` and adds:

- `mergeDefaults(array $defaults): array`, which receives defaults accumulated by earlier modifiers and returns the next defaults value;
- `getUniqueMiddleware(): array`, a map from a non-empty stable identity key to a middleware service ID or `MiddlewareSpecification`.

For an aggregating modifier, put its route-option accumulation in `mergeDefaults()`; its legacy `getDefaults()` result is not shallow-merged. Class modifiers are processed before method modifiers, and declaration order is retained within each target. A repeated identity key with the same normalized service or `MiddlewareSpecification::signature()` adds one middleware entry. `signature()` is the canonical, collision-free identity of a specification's service, factory, and arguments. A repeated key that identifies different middleware fails route extraction, rather than choosing one silently. Service IDs and `MiddlewareSpecification` values are different identities even if their textual values resemble each other.

This behavior is deliberately opt-in: existing `RouteAttributeModifierInterface` implementations keep their shallow default merge and may still add duplicate middleware entries. Middleware remains lazy; extraction, registration, and cache warmup do not resolve middleware or handler services.

This package requires `sirix/mezzio-routing-contracts ^1.2.1`, which exposes this interface and the canonical specification identity. Automatic multi-`MapRequest` integration requires a compatible `sirix/mezzio-valinor-request-mapper ^3.0`; mapper 3.0 has not yet been released.

### Combining Class and Method Attributes

If any method routes exist, class-level route attributes supply shared prefixes and middleware; they do not create standalone routes. Multiple class prefixes are concatenated in declaration order, not expanded into alternative routes:

```php
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;

#[Post('/api/', name: 'unused.class.name')]
#[Get('v1/')]
final class VersionedOrdersHandler
{
    #[Get('/orders', name: 'orders.list')]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse(['orders' => []]);
    }
}
```

Register this class as a container service and a route class with `handlers.mode=callable`. The result is one `GET /api/v1/orders` route named `orders.list`. Class-level HTTP methods and names are ignored when those attributes are prefixes; the method attribute supplies the route's methods and name (or Mezzio generates a name when it is omitted).

Surrounding whitespace is trimmed from paths and an empty path is invalid. A class prefix of exactly `/` is skipped. Prefix boundaries lose leading/trailing slashes, and leading slashes on the method path are removed when joining a non-empty prefix. A method path `/` under `/api/` becomes `/api/`; a method path `orders/` becomes `/api/orders/` and retains its trailing slash. If all prefixes are `/`, the method path is retained after whitespace trimming. Without a non-empty prefix, no leading slash is added; internal duplicate slashes are not normalized.

For a method route, incoming middleware runs in this order:

1. Middleware from all class route attributes, in declaration order.
2. Middleware from the method's route attribute.
3. Middleware from class modifier attributes.
4. Middleware from method modifier attributes.
5. The handler method.

Order within each middleware array is preserved. Method defaults override class defaults with the same key; later modifiers at the same level override earlier defaults. Duplicate middleware entries remain separate pipeline entries.

Inherited attributed methods remain routable on a concrete subclass and use that subclass's service ID. An override replaces the inherited method, so repeat its route/modifier attributes if it should remain routable. Class-level route and modifier attributes are read from the selected class, not inherited from its parent; inherited method modifiers are still read from the inherited method.

## Middleware Specifications

Attribute modifiers may return `MiddlewareSpecification` entries from `getMiddleware()` in
addition to service-id strings. A specification names the middleware service, a container
factory, and scalar-only arguments for that factory:

```php
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Sirix\Mezzio\Routing\Contracts\MiddlewareFactoryInterface;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;

public function getMiddleware(): array
{
    return [new MiddlewareSpecification(
        ProfileMiddleware::class,
        ProfileMiddlewareFactory::class,
        ['profile' => 'admin'],
    )];
}

final class ProfileMiddlewareFactory implements MiddlewareFactoryInterface
{
    public function create(ContainerInterface $container, MiddlewareSpecification $specification): MiddlewareInterface
    {
        return new ProfileMiddleware($specification->arguments['profile']);
    }
}
```

Specifications require a non-empty factory service ID; `null` and `''` are rejected during
route extraction, before cache generation or writing. For middleware without a factory, use
a service-id string instead. Factory IDs may be container aliases and need not be class names;
IDs are preserved verbatim. Structural validation does not resolve services or execute factories.

The factory is fetched from the application container and invoked lazily on the first request,
matching the existing service-id middleware path. `MiddlewareSpecification` arguments must be
scalars, `null`, or nested arrays of those values: route-cache artifacts use `var_export()` and
rehydrate specifications through `__set_state()`. See
[`sirix/mezzio-routing-contracts`](https://github.com/sirix777/mezzio-routing-contracts) for the
complete contract API.

The `getMiddleware()` fragment above belongs inside a modifier attribute. `ProfileMiddleware` and its factory are application classes: import their actual namespaces and register the factory service in the container. The specification's middleware class is constructed by that factory and does not also need a service registration unless the factory chooses to fetch it from the container.

### Route Defaults and Placeholders

The `getDefaults()` method supplies Mezzio route options. Whether these options become placeholder defaults or request attributes depends on the selected router adapter; this package only passes them through to Mezzio `Route::setOptions()`.

Example with an optional parameter using `sirix/mezzio-radixrouter` 3.2.3+:

Configure the Radix adapter as your application's `Mezzio\Router\RouterInterface`, register `ExportHandler::class` in the container and route class list, and set `routing_attributes.handlers.mode=callable`. This example uses `Laminas\Diactoros\Response\JsonResponse` (install `laminas/laminas-diactoros` if your application uses another response implementation).

```php
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;
use Sirix\Mezzio\Routing\Contracts\RouteAttributeModifierInterface;

#[\Attribute]
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
        $format = $request->getAttribute('format');

        return new JsonResponse(['format' => $format]);
    }
}
```

Notes:

- class-level and method-level modifiers are merged for method routes;
- method-level defaults override class-level defaults on the same key;
- middleware from modifiers is appended after middleware declared in `Route`/`Get` attributes.
- defaults are passed unchanged to Mezzio `Route::setOptions()`; consult your adapter for their meaning.
- integration tests use `sirix/mezzio-radixrouter` 3.2.3+: its parameter syntax is `:id` and `:id?` (for example, `/export/:format?`). Starting with 3.2.3, route defaults are merged into matched route parameters, so a missing optional parameter receives the default value as a request attribute. Path-captured values take precedence over defaults. Other router adapters have their own placeholder syntax and option handling; this is not a cross-adapter guarantee. Here `/export` returns `{"format":"json"}` and `/export/csv` returns `{"format":"csv"}`.
- when compiled cache is enabled, defaults are limited to cache-compatible values described in [Compiled Cache Behavior](#compiled-cache-behavior).

Integration tests exercise real `ServiceManager`, `RouteCollector`, routing and dispatch middleware with the Radix adapter in both uncached and prewarmed modes. Registration and warmup do not instantiate route services. On the first request, each lazy middleware wrapper resolves its service or specification factory result once and reuses it on subsequent requests; ServiceManager sharing also applies to service instances. Middleware must keep request-specific state in the request or local variables. Each pipeline invocation receives the current downstream handler, including when middleware invokes it more than once.

Routes with the same handler service, handler method, and middleware stack can share one pipeline, even when their paths differ. Resolved instances live as long as their lazy wrappers, including across requests in long-running workers; container non-sharing settings do not cause an already-resolved wrapper to fetch a fresh instance. Keep handlers and middleware free of stored per-request state, including tenant identity, authorization decisions, and request/response objects.

## Benchmarks

Run:

```bash
composer benchmark
composer benchmark-threshold
```

### Methodology

The original v1 figures were removed: they ran warm-cache iterations in the same PHP process after warmup, allowing `RouteCacheLoader` to return its process-static artifact. That excluded the PHP artifact `require` from the measured path.

The current benchmarks measure cold-start behavior more faithfully:

- cache-hit and no-cache samples run in separate PHP processes;
- cache-hit timing includes loading the generated PHP artifact with `require`;
- the threshold benchmark generates temporary corpora with real `#[Route]` attributes;
- its no-cache path measures class loading, reflection, attribute parsing, validation, normalization, and route registration;
- its cache-hit path uses the pre-warmed artifact for the same route corpus;
- every threshold sample verifies that it registered exactly the requested number of routes.
- large `unique` and `mixed` corpora pass their class inventory through a private JSON manifest rather than the child-process command line, so the default range remains valid beyond the platform argument-length limit.

The threshold benchmark compares uncached extraction with the one production artifact format. Its default `mixed` corpus models one handler with shared routes plus single-route handlers; set `BENCHMARK_PROFILE=shared`, `unique`, or `mixed` to select a corpus.

The route-provider benchmark's discovery-configured cache-hit scenarios intentionally skip discovery: a valid artifact is expected to bypass it.

### Current reference measurements

Local alternating fresh-process run, `41` samples, PHP `8.2.32`. These are registration/bootstrap microbenchmarks, not an HTTP latency claim.

| Corpus | Routes | Result |
|---|---:|---|
| `shared` | 12,800 | compiled `30.6591 ms` vs no-cache `62.4985 ms` (50.94% faster) |
| `mixed` | 3,200 | compiled `11.5022 ms` vs no-cache `28.5560 ms` (59.72% faster) |
| `unique` | 3,200 | compiled `13.3335 ms` vs no-cache `40.4937 ms` (67.07% faster) |

The common registration primitive is an intentional maintainability trade-off. Its isolated previous comparison at 12,800 routes showed `+7.8%` on cold registration and `8.47 ms` → `9.56 ms` (+12.9%) for the compiled loop, with memory effectively unchanged. We retain it because cold and cached registration share one semantic implementation; reconsider only if a stable CI performance budget or a representative production profile makes that cost material.

### Running a focused comparison

Results are host-sensitive microbenchmarks, not an end-to-end HTTP latency claim. Run a focused comparison on the target host with:

```bash
BENCHMARK_ITERATIONS=41 BENCHMARK_ROUTE_COUNTS=16,17 \
  BENCHMARK_PROFILE=mixed composer benchmark-threshold
```

Run test coverage with PCOV:

```bash
composer coverage
```

The coverage command requires the `pcov` PHP extension and runs PHPUnit with `pcov.enabled=1` and `pcov.directory=src`.

## Troubleshooting

- Service not found: register handler/action class in container.
- Route changes are not visible: build a new cache with `routing-attributes:cache:warmup` before activating the release.
- In long-running workers (RoadRunner/Swoole), reload/restart workers after the new artifact is active.
- Cache warmup fails: verify that the deployment user owns the configured cache path and can write its directory; the package intentionally does not alter ownership, mode bits, or ACLs.
