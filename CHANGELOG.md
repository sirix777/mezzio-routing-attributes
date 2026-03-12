# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Until `1.0.0` is released, backward compatibility is not guaranteed. Public APIs and configuration may change between releases, including minor and patch releases.

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
