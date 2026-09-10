# Benchmarks

This directory contains three performance benchmarks for `mezzio-routing-attributes`, measuring route registration, compiled cache loading, and middleware pipeline execution.

## Quick Start

```bash
# Run the route provider benchmark (fast, ~5 seconds)
php benchmarks/route-provider-benchmark.php
composer benchmark

# Run the cache threshold benchmark
php benchmarks/route-cache-threshold-benchmark.php
composer benchmark-threshold

# Run the focused pipeline benchmark (JSON on stdout)
php benchmarks/pipeline-benchmark.php --json
composer benchmark-pipeline
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

Both discovery scenarios use an isolated five-class fixture corpus that registers four routes. The benchmark fails if either strategy registers a different route count, keeping negative extractor fixtures out of the measurement without silently shrinking its discovery scope.

### How It Works

1. **Container setup:** Each iteration creates a `BenchmarkContainer` with all required services. Core infrastructure services are built through the same factories registered by `ConfigProvider`:
   - `AttributeRouteExtractorInterface` — real extractor with `RouteAttributeReader`, `RouteDefinitionBuilder`, etc.
   - `RouteRegistrarCacheInterface` — built by `CompiledRouteRegistrarCacheFactory`
   - `DuplicateRouteResolver` — built by `DuplicateRouteResolverFactory`
   - `MiddlewarePipelineFactory` — built by `MiddlewarePipelineFactoryFactory`
   - `DiscoveredClassesResolverInterface` — built by `DiscoveryClassMapResolverFactory`
   - Handler/middleware services: `PingHandler`, `PingRequestHandler`, `StackedHandler`, `StackFirstMiddleware`, `StackSecondMiddleware`, plus an isolated five-class discovery corpus with the same four routes

2. **Warmup:** Before measuring, the benchmark runs one warm-up iteration to populate the cache file.

3. **Measurement:** 100 iterations per scenario by default. Each cache-hit, no-cache, and cold-rebuild iteration runs in a fresh PHP process. Every iteration:
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

## 3. Middleware Pipeline Benchmark

**File:** `pipeline-benchmark.php`

Measures the current `MiddlewarePipelineFactory` execution cost, including its per-call `MiddlewareHandler` chain. It supports the pipeline performance measurement plan (plan 5); it does not measure route registration or end-to-end HTTP latency.

### Corpus and measurement intervals

There are 18 scenarios: 1, 5, 10, and 20 middleware entries for both service IDs and `MiddlewareSpecification`, each with a no-op or CPU/JSON handler, plus two direct-handler baselines. N means N middleware **plus** the terminal handler adapter, so a 20-middleware pipeline creates 21 chain wrappers. The direct baselines bypass the pipeline.

The no-op middleware increments an invocation counter and delegates; the no-op handler returns a prebuilt 204 response. The illustrative useful handler builds 100 records, computes 100 SHA-256 digests, and constructs a `JsonResponse`. It has no database, networking, or representative application workload.

Each sample constructs a fresh pipeline outside timing. `first` times one call including lazy service/factory resolution. An untimed order check and warmup follow; `warm` times a batch of resolved calls and reports nanoseconds per call. Classes and both resolution paths are primed before all reported samples, and samples share one PHP process. Consequently, `first` is not a cold PHP process, autoload, container construction, or pipeline construction measurement. Scenario order reverses between rounds to reduce systematic order bias; it cannot remove host scheduling, CPU frequency, or allocator effects.

### Configuration and correctness

| Environment variable | Default | Accepted integer range |
|---|---:|---:|
| `BENCHMARK_SAMPLES` | 7 | 1–100 |
| `BENCHMARK_REPEATS` | 5,000 | 1–100,000 |
| `BENCHMARK_WARMUP` | 100 | 1–10,000 |

```bash
BENCHMARK_SAMPLES=7 BENCHMARK_REPEATS=5000 BENCHMARK_WARMUP=100 \
  php benchmarks/pipeline-benchmark.php --json > /tmp/pipeline-performance.json
```

JSON is also the default without `--json`. It includes PHP version/binary, SAPI, OS, OPcache/JIT settings and activity, instrumentation extensions, configuration, every raw sample, and min/median/max summaries for both phases. `LOG_LEVEL=debug` writes sample progress to stderr outside timing. Invalid arguments/settings or failed correctness checks exit nonzero. There is no percentage CI threshold or blocking performance baseline for this benchmark.

Outside the timed intervals, the harness checks the actual first and final measured response status/body, middleware order, total middleware and handler calls, and resolution counters. Construction must resolve nothing; the first pipeline call must perform N + 1 container gets and, for specifications, N factory calls; subsequent calls must not resolve or create services again. The downstream handler throws if the terminal benchmark handler unexpectedly delegates. Counter increments remain inside timing, so these results include instrumentation overhead.

### Memory interpretation

Each interval runs garbage collection and resets PHP peak tracking before recording live usage. `retained_bytes` is the final live usage minus interval-start usage; `peak_bytes` is the reset peak above that start. Neither reports total allocated bytes, allocation count, process resident memory, or isolated wrapper allocation cost. The actual last measured response remains live during memory sampling and is validated after timing; the previous response is released before the next call.

Released wrappers can produce zero retained bytes while still costing allocations on every request. First-call retained memory includes resolved middleware. CPU/JSON measurements also include response/workload allocations: the reference runs retain 64,320 bytes for the last response, with a direct-handler peak of 77,200 bytes and a 20-middleware warm peak of 78,880 bytes. These are live/peak observations, not a leak or cumulative allocation estimate.

### Reference measurements and decision (2026-09-07)

Two separate CLI runs on Linux with PHP **8.2.33** (`/usr/bin/php82`), OPcache/JIT inactive and neither Xdebug nor PCOV loaded. Each run used the defaults above: seven samples per scenario, 5,000 calls per warm batch, and 100 warmup calls. Source JSON was captured locally as `/tmp/pipeline-performance-php82.json` (A) and `/tmp/pipeline-performance-php82-repeat.json` (B); these temporary files are not repository artifacts. The following summaries preserve the decision evidence here.

No-op corpus; first/warm values are medians in **µs per call**, shown as **A / B**. Peak values are bytes and identical in both runs.

| Kind | Middleware | First µs A / B | Warm µs A / B | First peak bytes | Warm peak bytes |
|---|---:|---:|---:|---:|---:|
| direct | 0 | 0.478 / 0.343 | 0.052 / 0.053 | 0 | 0 |
| service | 1 | 6.235 / 2.291 | 0.440 / 0.417 | 296 | 160 |
| service | 5 | 4.108 / 4.578 | 1.078 / 1.067 | 936 | 480 |
| service | 10 | 9.703 / 7.618 | 1.857 / 2.023 | 1,736 | 880 |
| service | 20 | 13.481 / 13.171 | 3.626 / 3.562 | 3,336 | 1,680 |
| specification | 1 | 3.516 / 3.460 | 0.383 / 0.417 | 296 | 160 |
| specification | 5 | 4.103 / 3.093 | 1.096 / 1.075 | 936 | 480 |
| specification | 10 | 5.963 / 6.442 | 1.977 / 1.980 | 1,736 | 880 |
| specification | 20 | 11.790 / 11.343 | 3.550 / 3.614 | 3,336 | 1,680 |

Warm retained memory is zero throughout the no-op corpus. First retained bytes are 136, 456, 856, and 1,656 for N = 1, 5, 10, and 20 in either resolution path. First-call timing is particularly noisy: service/1 ranges from 1.606 to 49.088 µs in A, explaining why its median can exceed service/5. Warm service/20 ranges are 3.233–3.797 µs (A) and 3.439–3.840 µs (B); specification/20 ranges are 3.272–3.872 µs and 3.479–3.840 µs.

Useful CPU/JSON corpus; warm **min / median / max**, in **µs per call**:

| Scenario | Run A | Run B |
|---|---:|---:|
| direct handler | 42.891 / 45.983 / 47.427 | 42.537 / 46.217 / 46.960 |
| service, 20 middleware | 45.011 / 46.927 / 51.484 | 46.080 / 48.485 / 54.214 |
| specification, 20 middleware | 45.459 / 47.841 / 53.637 | 46.369 / 47.498 / 51.219 |

A cross-version run with the same defaults on PHP **8.5.9** (`/tmp/pipeline-performance-php85.json`) had OPcache CLI disabled, OPcache inactive, JIT `disable` (configured buffer `64M`, not active), and no Xdebug/PCOV. The warm no-op 20-middleware medians were 3.069 µs (service) and 3.041 µs (specification), with the same 1,680-byte peak. Useful-work warm min/median/max values were 23.130/23.852/24.598 µs direct, 26.422/27.654/30.232 µs service/20, and 26.524/28.027/29.272 µs specification/20. This shows a measurable dispatch cost of about 3.8–4.2 µs (16–18% of this faster direct workload); the useful-work differences cannot all be dismissed as noise.

**Decision: retain the current production implementation.** On PHP 8.2 the 20-middleware no-op pipeline costs roughly 3.6 µs per call in total, compared with a roughly 0.05 µs direct call, and adds a 1,680-byte warm peak in this corpus. The PHP 8.2 useful-work ranges overlap broadly, while PHP 8.5 demonstrates measurable overhead for a faster handler. Across these runs the absolute dispatch cost remains a few microseconds. There is no representative application evidence establishing a material HTTP cost, and these measurements do not isolate how much time an allocation-reducing alternative could save.

The plan's conditional alternative-comparison branch was therefore not triggered: no alternative or production optimization was implemented. This does not establish that an improvement is impossible. Revisit with a representative application profile and stable measurement environment if pipeline execution is a meaningful part of its request budget. A microbenchmark improvement would still not guarantee an HTTP latency reduction.

The existing immutable per-call chain also keeps each invocation's downstream handler separate, including repeated downstream calls, nested pipeline entry, and overlapping execution using PHP Fibers. A shared mutable cursor would need to preserve all these semantics; caching a chain tied to an earlier request's downstream handler would be incorrect. Avoiding those risks and the maintenance cost of a second execution design is justified by the current evidence. Functional coverage was extended for nested reentry and Fibers; existing repeated-downstream, order, short-circuit, lazy-resolution, and downstream tests were preserved. The full suite passed on PHP 8.2–8.5 (318 tests, 1,189 assertions per runtime).

The Fiber check starts with a fresh pipeline and synchronous lazy resolution. Calls suspend inside the first middleware's `process()` after that middleware has resolved; the terminal service resolves on the first resume. This checks interleaved execution, but does not prove thread safety, Swoole behavior, or correctness of lazy factories that suspend while resolving services.

---

## Files

| File | Description | Tracked in Git |
|------|-------------|----------------|
| `route-provider-benchmark.php` | Route provider performance benchmark | Yes |
| `route-cache-threshold-benchmark.php` | Cache threshold benchmark | Yes |
| `pipeline-benchmark.php` | Pipeline first/warm execution and memory benchmark | Yes |
| `baseline.json` | Known-good performance baseline for regression detection | Yes |
| `report.json` | Latest benchmark report (auto-generated) | No (gitignored) |

---

## When to Run

- **Before release:** Run all three benchmarks to assess performance
- **After refactoring:** Run `route-provider-benchmark.php` to check for performance impact
- **After cache changes:** Run `route-cache-threshold-benchmark.php` to verify cache effectiveness
- **After pipeline changes:** Run `pipeline-benchmark.php` and compare raw distributions on the same runtime/host; it has no percentage gate
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

The route provider and cache threshold benchmarks use a minimal container that mirrors the real Mezzio container. The following services are registered (the pipeline benchmark uses its own focused counter-based container):

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
