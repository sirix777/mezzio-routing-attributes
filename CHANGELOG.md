# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Until `1.0.0` is released, backward compatibility is not guaranteed. Public APIs and configuration may change between releases, including minor and patch releases.

## [0.1.3] - 2026-03-14

### Changed

- Route definition cache payload format was optimized for lower memory overhead on cache-hit paths; cache format version was bumped, so old route cache files are now treated as cache misses and rebuilt
- Discovery class map cache load/validation path was simplified to reduce duplicate in-memory structures and unnecessary validation passes; cache format version was bumped, so old discovery cache files are now treated as cache misses and rebuilt
- Discovery file inventory representation and resolver internals were optimized to reduce cold-path allocation churn (including dedup/fingerprint construction improvements)
- Route registration path was streamlined to avoid avoidable temporary allocations when building middleware display/options and pipelines
- Benchmark output was hardened for repeatable comparisons: scenario ordering is now stable and scenario intent notes are included in report output
- Package defaults now keep caches disabled unless explicitly enabled by user configuration:
  - `routing_attributes.cache.enabled = false` (unchanged)
  - `routing_attributes.discovery.class_map_cache.enabled = false` (changed from `true`)
- README performance guidance now explicitly states that cache paths are not free and should be enabled only after measuring real benefit in the target project/environment

## [0.1.2] - 2026-03-13

### Changed

- CI now runs the QA matrix across all supported PHP versions (`8.2`, `8.3`, `8.4`, `8.5`) for both supported `mezzio/mezzio-router` branches (`^3.15` and `^4.1`)
- GitHub Actions workflow is now prepared for the Node.js 24 transition by forcing JavaScript actions onto Node 24 and updating official actions where newer major versions are available
- CLI route listing now defaults to upstream `mezzio:routes:list` behavior for classic routes defined outside attributes, and can optionally unwrap classic lazy-loaded routes to their resolved service name via `routing_attributes.route_list.classic_routes_middleware_display=resolved`
- CLI middleware filtering now matches the displayed middleware information more closely: attribute-defined routes are filterable by the rendered attribute pipeline, while classic routes continue to use the configured upstream/resolved classic-route display mode
- When `mezzio/mezzio-tooling` is not installed, CLI route listing now falls back to loading `config/routes.php` directly, so classic Mezzio routes still appear alongside attribute-defined routes

## [0.1.1] - 2026-03-12

### Changed

- CLI route listing now displays classic Mezzio routes more accurately when `mezzio:routes:list` is overridden by this package: lazy-loaded routes are shown by their underlying service name instead of `Mezzio\Middleware\LazyLoadingMiddleware`
- Optional console/tooling integration no longer requires direct compile-time references to `mezzio/mezzio-tooling` classes, allowing the package to be installed and analysed without that dependency
- CI now runs the full QA matrix across supported `mezzio/mezzio-router` versions again, including `^4.1`
- Documentation now explicitly covers hybrid routing setups where attribute-defined routes and classic routes from `config/routes.php` are used together in the same application
- Documentation now clarifies automatic `ConfigProvider` registration, support for both class-level and method-level route attributes, and that route parameter syntax depends on the configured Mezzio router implementation

## [0.1.0] - 2026-03-12

### Added

- Initial scaffold for `sirix/mezzio-routing-attributes`
- Route attributes and extractor skeleton
- Mezzio route provider integration primitives
- Method-level route attribute extraction for handler methods
- Support for route-specific middleware stacks that wrap a terminal handler method
- Class-level route prefixes inherited by method-level routes
- Class-level middleware inherited by method-level routes
- Attribute-aware CLI route listing via `routing-attributes:routes:list`
- Configurable override for `mezzio:routes:list` when `mezzio/mezzio-tooling` is installed
- Duplicate route detection for attribute-defined routes (by route name and by path+methods)
- Configurable duplicate handling strategy via `routing_attributes.duplicate_strategy` (`throw` or `ignore`)
- Package-specific runtime exceptions for invalid route middleware/handler services
- Optional require-based route cache via `routing_attributes.cache` (cache hit loads definitions from file; cache miss extracts and writes file)
- Cache metadata validation (`format_version`, duplicate strategy, classes fingerprint) with optional strict mode via `routing_attributes.cache.strict`
- Optional filesystem class discovery via `routing_attributes.discovery.paths` with require-based class map cache and optional `filemtime` validation
- Optional PSR-4 discovery strategy (`routing_attributes.discovery.strategy=psr4`) with configurable path-to-namespace mappings and token-parser fallback per file
- Configurable cache write failure strategies via `routing_attributes.cache.write_fail_strategy` and `routing_attributes.discovery.class_map_cache.write_fail_strategy` (`ignore|throw`)
- Configurable handler mode via `routing_attributes.handlers.mode` (`psr15|callable`) to allow method-level routes on plain controller/action classes
- Console command now supports non-tooling mode; when tooling is absent, `mezzio:routes:list` is registered as an alias to `routing-attributes:routes:list`
- CLI auto-registration is now tied to `laminas/laminas-cli`; plain Symfony Console setups require manual command registration
- Micro-benchmark script (`composer benchmark`) with warm/cold/discovery scenarios and JSON report output
- CI now runs benchmark reporting as a non-blocking step and uploads benchmark artifacts
- Extraction-time validation for method-level routes: target method visibility, request-argument signature compatibility, and declared return type compatibility
- Additional extractor test fixtures and negative tests for non-public methods, invalid signatures, and invalid declared return types
- Class-level route attributes now act as shared route metadata when method-level routes exist on the same class
- Documentation updated to reflect method-level handlers, middleware pipelines, class-level prefixes, and CLI integration
- Route cache hydration is now deterministic and fail-fast: any malformed route entry invalidates the whole payload (no partial route loading)
- In strict route-cache mode, malformed route payload now throws explicit `InvalidConfigurationException` with route entry context
- Cache write failure exceptions now include captured filesystem error reason for both route cache and discovery class map cache
- Method-level route validation now primarily happens during extraction; runtime pipeline checks remain as defense-in-depth
- `RoutingAttributesConfig::fromRootConfig()` refactored into focused internal parsers without behavior change
- Extraction-time type validation is stricter for advanced signatures: union return types must be fully `ResponseInterface`-compatible and intersection request parameter types must be `ServerRequestInterface`-compatible
