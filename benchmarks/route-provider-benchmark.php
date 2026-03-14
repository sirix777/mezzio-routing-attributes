<?php

declare(strict_types=1);

use Mezzio\Router\Route;
use Mezzio\Router\RouteCollectorInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Sirix\Mezzio\Routing\Attributes\AttributeRouteProviderFactory;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractor;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\PingHandler;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\PingRequestHandler;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\StackFirstMiddleware;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\StackSecondMiddleware;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\StackedHandler;

require dirname(__DIR__) . '/vendor/autoload.php';

final class BenchmarkContainer implements ContainerInterface
{
    /** @var array<string, mixed> */
    private array $services;
    public int $getCalls = 0;

    /**
     * @param array<string, mixed> $services
     */
    public function __construct(array $services)
    {
        $this->services = $services;
    }

    public function get(string $id): mixed
    {
        $this->getCalls++;

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}

final class BenchmarkCollector implements RouteCollectorInterface
{
    public int $routeCalls = 0;

    public function route(string $path, MiddlewareInterface $middleware, ?array $methods = null, ?string $name = null): Route
    {
        $this->routeCalls++;

        return new Route($path, $middleware, $methods, $name);
    }

    public function get(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
    {
        return $this->route($path, $middleware, ['GET'], $name);
    }

    public function post(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
    {
        return $this->route($path, $middleware, ['POST'], $name);
    }

    public function put(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
    {
        return $this->route($path, $middleware, ['PUT'], $name);
    }

    public function patch(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
    {
        return $this->route($path, $middleware, ['PATCH'], $name);
    }

    public function delete(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
    {
        return $this->route($path, $middleware, ['DELETE'], $name);
    }

    public function any(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
    {
        return $this->route($path, $middleware, null, $name);
    }

    public function getRoutes(): array
    {
        return [];
    }
}

/**
 * @param list<float> $samples
 *
 * @return array{avg_ms: float, median_ms: float, min_ms: float, max_ms: float}
 */
function summarizeSamples(array $samples): array
{
    sort($samples);
    $count = count($samples);
    if (0 === $count) {
        return [
            'avg_ms' => 0.0,
            'median_ms' => 0.0,
            'min_ms' => 0.0,
            'max_ms' => 0.0,
        ];
    }

    $avg = array_sum($samples) / $count;
    $middle = (int) floor($count / 2);
    $median = 0 === $count % 2
        ? ($samples[$middle - 1] + $samples[$middle]) / 2
        : $samples[$middle];

    return [
        'avg_ms' => round($avg, 4),
        'median_ms' => round($median, 4),
        'min_ms' => round(min($samples), 4),
        'max_ms' => round(max($samples), 4),
    ];
}

/**
 * @param callable(): array{
 *     elapsed_ms: float,
 *     peak_memory_usage_kb: float,
 *     container_get_calls: int,
 *     route_calls: int
 * } $iteration
 *
 * @return array{
 *     avg_ms: float,
 *     median_ms: float,
 *     min_ms: float,
 *     max_ms: float,
 *     avg_peak_memory_usage_kb: float,
 *     median_peak_memory_usage_kb: float,
 *     max_peak_memory_usage_kb: float,
 *     avg_container_get_calls: float,
 *     avg_route_calls: float
 * }
 */
function runScenario(callable $iteration, int $iterations): array
{
    $durations = [];
    $peakMemoryUsages = [];
    $containerCalls = 0;
    $routeCalls = 0;

    for ($i = 0; $i < $iterations; $i++) {
        $sample = $iteration();
        $durations[] = $sample['elapsed_ms'];
        $peakMemoryUsages[] = $sample['peak_memory_usage_kb'];
        $containerCalls += $sample['container_get_calls'];
        $routeCalls += $sample['route_calls'];
    }

    $summary = summarizeSamples($durations);
    $peakMemorySummary = summarizeSamples($peakMemoryUsages);
    $summary['avg_peak_memory_usage_kb'] = $peakMemorySummary['avg_ms'];
    $summary['median_peak_memory_usage_kb'] = $peakMemorySummary['median_ms'];
    $summary['max_peak_memory_usage_kb'] = $peakMemorySummary['max_ms'];
    $summary['avg_container_get_calls'] = round($containerCalls / $iterations, 2);
    $summary['avg_route_calls'] = round($routeCalls / $iterations, 2);

    return $summary;
}

function runProvider(array $config): array
{
    $extractor = new AttributeRouteExtractor();
    $container = new BenchmarkContainer([
        'config' => $config,
        AttributeRouteExtractorInterface::class => $extractor,
        PingHandler::class => new PingHandler(),
        PingRequestHandler::class => new PingRequestHandler(),
        StackedHandler::class => new StackedHandler(),
        StackFirstMiddleware::class => new StackFirstMiddleware(),
        StackSecondMiddleware::class => new StackSecondMiddleware(),
    ]);

    $provider = (new AttributeRouteProviderFactory())($container);
    $collector = new BenchmarkCollector();
    gc_collect_cycles();
    $memoryBefore = memory_get_usage();
    if (function_exists('memory_reset_peak_usage')) {
        memory_reset_peak_usage();
    }
    $start = hrtime(true);
    $provider->registerRoutes($collector);
    $elapsed = (hrtime(true) - $start) / 1_000_000;
    $peakMemoryUsage = max(memory_get_peak_usage() - $memoryBefore, 0) / 1024;

    return [
        'elapsed_ms' => $elapsed,
        'peak_memory_usage_kb' => round($peakMemoryUsage, 4),
        'container_get_calls' => $container->getCalls,
        'route_calls' => $collector->routeCalls,
    ];
}

$iterations = 100;
$tempPrefix = sys_get_temp_dir() . '/mezzio-routing-attributes-bench-' . uniqid('', true);
$routeCacheFile = $tempPrefix . '-routes.php';
$classMapCacheFile = $tempPrefix . '-classmap.php';
$discoveryPath = dirname(__DIR__) . '/test/Extractor/Fixture';

$manualConfig = [
    'routing_attributes' => [
        'classes' => [PingHandler::class],
        'cache' => [
            'enabled' => true,
            'file' => $routeCacheFile,
            'strict' => false,
            'write_fail_strategy' => 'ignore',
        ],
    ],
];

$manualNoCacheConfig = [
    'routing_attributes' => [
        'classes' => [PingHandler::class],
        'cache' => [
            'enabled' => false,
        ],
    ],
];

if (file_exists($routeCacheFile)) {
    unlink($routeCacheFile);
}
runProvider($manualConfig);

$warmManual = runScenario(static fn (): array => runProvider($manualConfig), $iterations);

$noCacheManual = runScenario(static fn (): array => runProvider($manualNoCacheConfig), $iterations);

$coldManual = runScenario(static function () use ($manualConfig, $routeCacheFile): array {
    if (file_exists($routeCacheFile)) {
        unlink($routeCacheFile);
    }

    return runProvider($manualConfig);
}, $iterations);

$discoveryBaseConfig = [
    'routing_attributes' => [
        'classes' => [],
        'cache' => [
            'enabled' => true,
            'file' => $routeCacheFile,
            'strict' => false,
            'write_fail_strategy' => 'ignore',
        ],
        'discovery' => [
            'enabled' => true,
            'paths' => [$discoveryPath],
            'strategy' => 'token',
            'class_map_cache' => [
                'enabled' => true,
                'file' => $classMapCacheFile,
                'write_fail_strategy' => 'ignore',
            ],
        ],
    ],
];

$discoveryValidateTrue = $discoveryBaseConfig;
$discoveryValidateTrue['routing_attributes']['discovery']['class_map_cache']['validate'] = true;

if (file_exists($routeCacheFile)) {
    unlink($routeCacheFile);
}
if (file_exists($classMapCacheFile)) {
    unlink($classMapCacheFile);
}
runProvider($discoveryValidateTrue);
$warmDiscoveryValidateTrue = runScenario(static fn (): array => runProvider($discoveryValidateTrue), $iterations);

$discoveryValidateFalse = $discoveryBaseConfig;
$discoveryValidateFalse['routing_attributes']['discovery']['class_map_cache']['validate'] = false;
if (file_exists($routeCacheFile)) {
    unlink($routeCacheFile);
}
if (file_exists($classMapCacheFile)) {
    unlink($classMapCacheFile);
}
runProvider($discoveryValidateFalse);
$warmDiscoveryValidateFalse = runScenario(static fn (): array => runProvider($discoveryValidateFalse), $iterations);

$discoveryPsr4ValidateTrue = $discoveryBaseConfig;
$discoveryPsr4ValidateTrue['routing_attributes']['discovery']['strategy'] = 'psr4';
$discoveryPsr4ValidateTrue['routing_attributes']['discovery']['psr4'] = [
    'mappings' => [
        $discoveryPath => 'SirixTest\\Mezzio\\Routing\\Attributes\\Extractor\\Fixture\\',
    ],
    'fallback_to_token' => true,
];
$discoveryPsr4ValidateTrue['routing_attributes']['discovery']['class_map_cache']['validate'] = true;

if (file_exists($routeCacheFile)) {
    unlink($routeCacheFile);
}
if (file_exists($classMapCacheFile)) {
    unlink($classMapCacheFile);
}
runProvider($discoveryPsr4ValidateTrue);
$warmDiscoveryPsr4ValidateTrue = runScenario(static fn (): array => runProvider($discoveryPsr4ValidateTrue), $iterations);

$discoveryPsr4ValidateFalse = $discoveryPsr4ValidateTrue;
$discoveryPsr4ValidateFalse['routing_attributes']['discovery']['class_map_cache']['validate'] = false;
if (file_exists($routeCacheFile)) {
    unlink($routeCacheFile);
}
if (file_exists($classMapCacheFile)) {
    unlink($classMapCacheFile);
}
runProvider($discoveryPsr4ValidateFalse);
$warmDiscoveryPsr4ValidateFalse = runScenario(static fn (): array => runProvider($discoveryPsr4ValidateFalse), $iterations);

$report = [
    'php_version' => PHP_VERSION,
    'timestamp' => date('c'),
    'iterations' => $iterations,
    'scenario_notes' => [
        'warm_cache_hit_manual' => 'Route cache hit with explicit class list. Primary signal for route-cache load-path memory.',
        'no_cache_manual' => 'Manual class list without route cache. Lower-bound reference for registration overhead.',
        'cold_cache_rebuild_manual' => 'Route cache cold rebuild from explicit class list. Captures extraction/write cost.',
        'warm_cache_hit_discovery_validate_true' => 'Discovery + class-map cache hit with validation enabled. Stresses validation memory path.',
        'warm_cache_hit_discovery_validate_false' => 'Discovery + class-map cache hit with validation disabled. Measures best-case discovery cache hit.',
        'warm_cache_hit_discovery_psr4_validate_true' => 'PSR-4 discovery + class-map cache hit with validation enabled.',
        'warm_cache_hit_discovery_psr4_validate_false' => 'PSR-4 discovery + class-map cache hit with validation disabled.',
    ],
    'scenarios' => [
        'warm_cache_hit_manual' => $warmManual,
        'no_cache_manual' => $noCacheManual,
        'cold_cache_rebuild_manual' => $coldManual,
        'warm_cache_hit_discovery_validate_true' => $warmDiscoveryValidateTrue,
        'warm_cache_hit_discovery_validate_false' => $warmDiscoveryValidateFalse,
        'warm_cache_hit_discovery_psr4_validate_true' => $warmDiscoveryPsr4ValidateTrue,
        'warm_cache_hit_discovery_psr4_validate_false' => $warmDiscoveryPsr4ValidateFalse,
    ],
    'budget' => [
        'cache_hit_regression_max_percent' => 5.0,
    ],
];

$baselineFile = dirname(__DIR__) . '/benchmarks/baseline.json';
if (file_exists($baselineFile)) {
    $decoded = json_decode((string) file_get_contents($baselineFile), true);
    if (is_array($decoded) && isset($decoded['scenarios']['warm_cache_hit_manual']['median_ms'])) {
        $baselineMedian = (float) $decoded['scenarios']['warm_cache_hit_manual']['median_ms'];
        $currentMedian = (float) $warmManual['median_ms'];
        if ($baselineMedian > 0.0) {
            $regression = (($currentMedian - $baselineMedian) / $baselineMedian) * 100;
            $report['comparison'] = [
                'baseline_warm_cache_hit_manual_median_ms' => round($baselineMedian, 4),
                'current_warm_cache_hit_manual_median_ms' => round($currentMedian, 4),
                'regression_percent' => round($regression, 2),
                'within_budget' => $regression <= 5.0,
            ];
        }
    }
}

$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (false === $json) {
    fwrite(STDERR, "Failed to encode benchmark report.\n");
    exit(1);
}

$outFile = dirname(__DIR__) . '/benchmarks/report.json';
if (false === file_put_contents($outFile, $json)) {
    fwrite(STDERR, "Failed to write benchmark report file.\n");
    exit(1);
}

echo "# Route Provider Benchmark\n\n";
echo sprintf("- PHP: `%s`\n", $report['php_version']);
echo sprintf("- Iterations per scenario: `%d`\n\n", $iterations);
echo "## Scenario Intent\n\n";
foreach ($report['scenario_notes'] as $name => $note) {
    echo sprintf("- `%s`: %s\n", $name, $note);
}
echo "\n";
echo "| Scenario | median ms | avg ms | min ms | max ms | median peak KB | avg peak KB | max peak KB | avg container get() | avg routes |\n";
echo "|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|\n";

foreach ($report['scenarios'] as $name => $metrics) {
    $scenarioName = $name;
    echo sprintf(
        "| `%s` | %s | %s | %s | %s | %s | %s | %s | %s | %s |\n",
        $scenarioName,
        number_format((float) $metrics['median_ms'], 4, '.', ''),
        number_format((float) $metrics['avg_ms'], 4, '.', ''),
        number_format((float) $metrics['min_ms'], 4, '.', ''),
        number_format((float) $metrics['max_ms'], 4, '.', ''),
        number_format((float) $metrics['median_peak_memory_usage_kb'], 4, '.', ''),
        number_format((float) $metrics['avg_peak_memory_usage_kb'], 4, '.', ''),
        number_format((float) $metrics['max_peak_memory_usage_kb'], 4, '.', ''),
        number_format((float) $metrics['avg_container_get_calls'], 2, '.', ''),
        number_format((float) $metrics['avg_route_calls'], 2, '.', '')
    );
}

echo "\n";
if (isset($report['comparison'])) {
    echo "## Baseline Comparison\n\n";
    echo sprintf(
        "- Warm cache-hit median regression: `%s%%` (budget: `<= 5%%`) -> `%s`\n",
        number_format((float) $report['comparison']['regression_percent'], 2, '.', ''),
        $report['comparison']['within_budget'] ? 'OK' : 'OUT_OF_BUDGET'
    );
} else {
    echo "## Baseline Comparison\n\n";
    echo "- No `benchmarks/baseline.json` found; comparison skipped.\n";
}

echo "\nReport JSON: `benchmarks/report.json`\n";
