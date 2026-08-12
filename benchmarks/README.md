# Benchmarks

This directory contains performance benchmarks for `mezzio-routing-attributes`. There are two benchmarks that measure different aspects of the library's performance.

## Quick Start

```bash
# Run the route provider benchmark (fast, ~5 seconds)
php benchmarks/route-provider-benchmark.php

# Run the cache threshold benchmark
php benchmarks/route-cache-threshold-benchmark.php
```

---

## 1. Route Provider Benchmark

**File:** `route-provider-benchmark.php`

### Purpose

Measures the raw performance of `AttributeRouteProvider::registerRoutes()` — the core method that extracts routes from PHP attributes and registers them with the Mezzio router. This benchmark answers the question: "How fast can we go from class attributes to registered routes?"

### What It Measures

Each iteration creates a fresh container with all required services, builds an `AttributeRouteProvider` via `AttributeRouteProviderFactory`, and calls `registerRoutes()`. Warm-cache iterations run in separate PHP processes, so the measured interval includes loading the artifact with `require` and cannot reuse `RouteCacheLoader`'s process-static cache. The benchmark records:

| Metric | Description |
|--------|-------------|
| `elapsed_ms` | Wall-clock time for `registerRoutes()` only (not container setup) |
| `peak_memory_usage_kb` | Peak memory allocated during `registerRoutes()` |
| `route_calls` | Number of routes registered with the collector |

### Scenarios

| Scenario | What It Tests | Container Setup |
|----------|---------------|-----------------|
| `warm_cache_hit_manual` | **Primary regression signal.** Route cache file exists and is valid. Measures the fast-path where routes are loaded from cache, not extracted. | Explicit class list (`[PingHandler::class]`), cache enabled |
| `no_cache_manual` | Baseline reference. No cache, routes extracted every time. Lower-bound for registration overhead. | Explicit class list, cache disabled |
| `cold_cache_rebuild_manual` | Cache miss. Cache file is deleted before each iteration, forcing full extraction + cache write. Captures the cost of first-time registration. | Explicit class list, cache enabled, file deleted each iteration |
| `warm_cache_hit_discovery_token` | Cache hit with token discovery configuration. Discovery is skipped on a valid hit. | Empty class list, discovery enabled (token strategy), cache enabled |
| `warm_cache_hit_discovery_psr4` | Cache hit with PSR-4 discovery configuration. Discovery is skipped on a valid hit. | Empty class list, discovery enabled (PSR-4 strategy), cache enabled |

### How It Works

1. **Container setup:** Each iteration creates a `BenchmarkContainer` with all required services. Core infrastructure services are built through the same factories registered by `ConfigProvider`:
   - `AttributeRouteExtractorInterface` — real extractor with `RouteAttributeReader`, `RouteDefinitionBuilder`, etc.
   - `RouteRegistrarCacheInterface` — built by `CompiledRouteRegistrarCacheFactory`
   - `DuplicateRouteResolver` — built by `DuplicateRouteResolverFactory`
   - `MiddlewarePipelineFactory` — built by `MiddlewarePipelineFactoryFactory`
   - `DiscoveredClassesResolverInterface` — built by `DiscoveryClassMapResolverFactory`
   - Handler/middleware services: `PingHandler`, `PingRequestHandler`, `StackedHandler`, `StackFirstMiddleware`, `StackSecondMiddleware`

2. **Warmup:** Before measuring, the benchmark runs one warm-up iteration to populate the cache file.

3. **Measurement:** 20 iterations per scenario by default. Each cache-hit, no-cache, and cold-rebuild iteration runs in a fresh PHP process. Every iteration:
   - Calls `gc_collect_cycles()` to minimize GC interference
   - Records `memory_get_usage()` before
   - Resets peak memory tracking
   - Times `registerRoutes()` with `hrtime(true)`
   - Records peak memory delta

4. **Aggregation:** Results are summarized as median, avg, min, max for timing; avg/median/max for memory.

5. **Baseline comparison:** The benchmark compares the `warm_cache_hit_manual` median only with a baseline recorded using the same fresh-process artifact-loading methodology. A regression beyond the **<= 5%** budget exits non-zero.

### Output

- Console: Markdown table with all metrics
- `benchmarks/report.json`: Full JSON report (gitignored)

### Baseline Comparison Logic

```
regression_percent = ((current_median - baseline_median) / baseline_median) * 100
within_budget = regression_percent <= 5.0
```

- **Negative** regression means the code is **faster** than baseline (good)
- **Positive** regression within budget (<= 5%) is acceptable
- **Positive** regression exceeding budget signals a performance problem

### Historical Results (v1 artifact; not comparable with v2)

> PHP `8.2.30` | 100 iterations | Manual: 2 routes (PingHandler) | Discovery: 4 routes from 5 fixture classes

| Scenario | median ms | avg ms | min ms | max ms | median peak KB | avg peak KB | max peak KB | avg routes |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| `warm_cache_hit_manual` | 0.0015 | 0.0016 | 0.0014 | 0.0055 | 2.0156 | 2.0156 | 2.0156 | 2.00 |
| `no_cache_manual` | 0.0059 | 0.0063 | 0.0055 | 0.0200 | 3.4453 | 3.4453 | 3.4453 | 2.00 |
| `cold_cache_rebuild_manual` | 0.0443 | 0.0598 | 0.0282 | 0.2351 | 5.9063 | 5.9063 | 5.9063 | 2.00 |
| `warm_cache_hit_discovery_token` | 0.0034 | 0.0035 | 0.0031 | 0.0084 | 3.1406 | 3.1406 | 3.1406 | 4.00 |
| `warm_cache_hit_discovery_psr4` | 0.0034 | 0.0036 | 0.0031 | 0.0070 | 3.1406 | 3.1406 | 3.1406 | 4.00 |

These figures use the former in-process methodology, which could reuse `RouteCacheLoader`'s process-static artifact. They are retained only as a v1 historical record. Collect a fresh baseline for the current artifact format before drawing performance conclusions.

---

## 2. Route Cache Threshold Benchmark

**File:** `route-cache-threshold-benchmark.php`

### Purpose

Determines the **minimum route count** at which the compiled route cache becomes faster than no-cache. This answers the question: "At how many routes does caching start to pay off?"

### What It Measures

For each route count, the benchmark compares the single production artifact format with uncached extraction.

| Configuration | Description |
|---------------|-------------|
| `no-cache` | Routes extracted fresh every time, no cache file |
| `compiled` | Routes loaded from a pre-warmed production PHP artifact |

For each configuration it records:

| Metric | Description |
|--------|-------------|
| `elapsed_ms` | Wall-clock time for `registerRoutes()` |
| `peak_kb` | Peak memory allocated during `registerRoutes()` |
| `usage_delta_kb` | Live memory change (`memory_get_usage()` after - before). This is non-peak, showing actual retained memory. |

### Route Counts

The benchmark tests these route counts:

```
10, 16, 17, 25, 50, 100, 200, 400, 800, 1600, 2400, 3200, 4800, 6400, 9600, 12800
```

### How It Works

1. **Generated attribute corpus:** For every route count, the benchmark creates a temporary corpus with real repeatable `#[Route]` attributes. The default `mixed` profile places half the routes on one handler and the remainder on separate handlers. `shared` puts every route on one handler; `unique` gives each route a separate handler. The no-cache sample loads the corpus during the measured interval and runs production extraction; the cache-hit sample loads the pre-warmed artifact for the same corpus.

2. **For each route count:**
   - Run `no-cache` scenario (20 iterations, cache disabled; each sample uses a fresh PHP process)
   - Pre-warm the production artifact in an isolated process.
   - Run `compiled` (20 iterations by default; every warm-cache sample uses a fresh PHP process and includes artifact loading).
   - Calculate speedup: `((no_cache_median - cache_hit_median) / no_cache_median) * 100`

   Each sample verifies after timing that its collector received exactly the requested route count. A malformed artifact therefore cannot look fast by registering fewer routes.

3. **Cache-win detection:** The benchmark tracks the first route count where `cache_hit_median <= no_cache_median`. This is the "break-even" point.

4. **Cleanup and isolation:** The harness creates a random private `0700` directory beneath the system temp directory. Generated PHP corpus, cache files, and the `0600` JSON class manifest used by child samples stay inside it and are deleted after measurement. The manifest avoids passing thousands of class names through `proc_open()` arguments, so `unique` and `mixed` profiles work at the default maximum corpus size without relying on the host argument-length limit.

### Output

Console table with columns:

| Column | Description |
|--------|-------------|
| `Routes` | Number of routes |
| `no-cache median ms` | Median time without cache |
| `compiled median ms` | Median time with the production artifact |
| `compiled speedup %` | Positive = cache is faster, negative = cache is slower |
| `no-cache median peak KB` | Peak memory without cache |
| `compiled median peak KB` | Peak memory with the production artifact |
| `no-cache median usage delta KB` | Retained memory without cache |
| `compiled median usage delta KB` | Retained memory with the production artifact |

### Interpreting Results

- **Speedup % > 0**: Cache is faster than no-cache at this route count
- **Speedup % < 0**: Cache is slower (overhead exceeds benefit)
- **First cache-win point**: The minimum measured route count where the production artifact becomes beneficial

Select a corpus profile with `BENCHMARK_PROFILE`:

```bash
BENCHMARK_PROFILE=shared php benchmarks/route-cache-threshold-benchmark.php
BENCHMARK_PROFILE=unique php benchmarks/route-cache-threshold-benchmark.php
BENCHMARK_PROFILE=mixed php benchmarks/route-cache-threshold-benchmark.php
```

`mixed` is the default. Use `BENCHMARK_ROUTE_COUNTS` and `BENCHMARK_ITERATIONS` to narrow or stabilize a run. This regression benchmark covers the one supported artifact format; it does not make layout-selection or threshold claims.

### Current reference measurements

Local alternating fresh-process run, PHP `8.2.32`, 41 samples. These figures are recorded for comparison on this environment; they are not a checked-in blocking baseline.

| Corpus | Routes | Current result |
|---|---:|---|
| `shared` | 12,800 | compiled `30.6591 ms`, no-cache `62.4985 ms`, speedup `50.94%` |
| `mixed` | 3,200 | compiled `11.5022 ms`, no-cache `28.5560 ms`, speedup `59.72%` |
| `unique` | 3,200 | compiled `13.3335 ms`, no-cache `40.4937 ms`, speedup `67.07%` |

The shared scalar registration primitive keeps cold and compiled semantics aligned. Its isolated previous 12,800-route comparison versus inlined loops was `+7.8%` on cold registration and `8.47 ms` → `9.56 ms` (`+12.9%`) for compiled registration, with memory difference below 1 KB. This is an accepted trade-off until a fixed CI host and a representative application corpus justify a tighter budget.

### Historical results

> PHP `8.2.30` | 20 iterations per point | Cache backend: compiled

| Routes | no-cache median ms | compiled median ms | compiled speedup % | no-cache median peak KB | compiled median peak KB | no-cache median usage delta KB | compiled median usage delta KB |
|---:|---:|---:|---:|---:|---:|---:|---:|
| 10 | 0.0283 | 0.0112 | 60.42 | 11.4844 | 8.1484 | 9.1875 | 7.9375 |
| 25 | 0.0741 | 0.0173 | 76.65 | 26.8750 | 19.0078 | 21.9219 | 18.7969 |
| 50 | 0.0982 | 0.0258 | 73.73 | 52.7344 | 37.2109 | 43.2500 | 37.0000 |
| 100 | 0.1540 | 0.0505 | 67.21 | 104.4531 | 73.6172 | 85.9063 | 73.4063 |
| 200 | 0.3027 | 0.1200 | 60.36 | 213.8906 | 149.4297 | 174.2188 | 149.2188 |
| 400 | 0.6079 | 0.2315 | 61.92 | 418.7656 | 294.0547 | 343.8438 | 293.8438 |
| 800 | 1.2199 | 0.4749 | 61.07 | 828.5156 | 583.3047 | 683.0938 | 583.0938 |
| 1600 | 2.6012 | 0.9245 | 64.46 | 1652.7109 | 1161.8047 | 1366.2891 | 1161.5938 |
| 2400 | 3.8923 | 1.3942 | 64.18 | 2510.4609 | 1756.3047 | 2067.0391 | 1756.0938 |
| 3200 | 5.1543 | 1.8972 | 63.19 | 3304.2109 | 2318.8047 | 2735.7891 | 2318.5938 |
| 4800 | 7.7147 | 2.8661 | 62.85 | 5019.7109 | 3507.8047 | 4137.2891 | 3507.5938 |
| 6400 | 10.4122 | 4.0473 | 61.13 | 6607.2109 | 4632.8047 | 5474.7891 | 4632.5938 |
| 9600 | 15.9075 | 5.9559 | 62.56 | 10038.2109 | 7010.8047 | 8277.7891 | 7010.5938 |
| 12800 | 21.5622 | 8.5712 | 60.25 | 13213.2109 | 9260.8047 | 10952.7891 | 9260.5938 |

These figures use a former artifact and in-process loader cache. They are retained only as historical context, not as a threshold or format-selection recommendation; rerun the current benchmark on the target host.

---

## Files

| File | Description | Tracked in Git |
|------|-------------|----------------|
| `route-provider-benchmark.php` | Route provider performance benchmark | Yes |
| `route-cache-threshold-benchmark.php` | Cache threshold benchmark | Yes |
| `baseline.json` | Known-good performance baseline for regression detection | Yes |
| `report.json` | Latest benchmark report (auto-generated) | No (gitignored) |

---

## When to Run

- **Before release:** Run both benchmarks to detect regressions
- **After refactoring:** Run `route-provider-benchmark.php` to check for performance impact
- **After cache changes:** Run `route-cache-threshold-benchmark.php` to verify cache effectiveness
- **CI integration:** The `route-provider-benchmark.php` benchmark compares against `baseline.json` and exits with a non-zero status if regression exceeds the budget (5%)

---

## Updating the Baseline

The `baseline.json` file contains reference performance numbers from a known-good state. Update it when:

1. You've made a deliberate performance improvement and want to lock in the new numbers
2. The benchmark structure has changed (new scenarios, different measurement approach)
3. You've upgraded PHP versions and want to re-baseline

To update:

```bash
# Run the benchmark (this generates report.json)
php benchmarks/route-provider-benchmark.php

# Copy the report as the new baseline
cp benchmarks/report.json benchmarks/baseline.json
```

**Important:** Only update the baseline after verifying that the current performance is acceptable. The baseline is the "contract" that future runs are compared against.

---

## Container Services

Both benchmarks use a minimal container that mirrors the real Mezzio container. The following services are registered:

| Service | Implementation | Why |
|---------|---------------|-----|
| `config` | Array with `routing_attributes` section | Drives all behavior |
| `RoutingAttributesConfig` | Parsed shared configuration service | Mirrors `ConfigProvider` wiring |
| `AttributeRouteExtractorInterface` | Real `AttributeRouteExtractor` | Extracts routes from classes |
| `RouteRegistrarCacheInterface` | `CompiledRouteRegistrarCacheFactory` output | Mirrors package cache wiring |
| `DuplicateRouteResolver` | `DuplicateRouteResolverFactory` output | Handles duplicate route detection |
| `MiddlewarePipelineFactory` | `MiddlewarePipelineFactoryFactory` output | Builds middleware pipelines for routes |
| `DiscoveredClassesResolverInterface` | `DiscoveryClassMapResolverFactory` output | Mirrors package discovery wiring |
| Handler/middleware services | Real instances | Simulate real application services |

Factory-built services that depend on the container are added via `$container->set()` after initial construction.
