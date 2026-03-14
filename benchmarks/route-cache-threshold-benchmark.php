<?php

declare(strict_types=1);

use Mezzio\Router\Route;
use Mezzio\Router\RouteCollectorInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\AttributeRouteProviderFactory;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;
use Sirix\Mezzio\Routing\Attributes\RouteDefinition;

require dirname(__DIR__) . '/vendor/autoload.php';

final class ThresholdBenchmarkContainer implements ContainerInterface
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

final class ThresholdBenchmarkCollector implements RouteCollectorInterface
{
    public int $routeCalls = 0;
    /** @var list<Route> */
    private array $routes = [];

    public function route(string $path, MiddlewareInterface $middleware, ?array $methods = null, ?string $name = null): Route
    {
        $this->routeCalls++;

        $route = new Route($path, $middleware, $methods, $name);
        $this->routes[] = $route;

        return $route;
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
        return $this->routes;
    }
}

final class SyntheticExtractor implements AttributeRouteExtractorInterface
{
    public function __construct(private int $routeCount) {}

    public function extract(array $classes): array
    {
        $routes = [];
        for ($i = 1; $i <= $this->routeCount; $i++) {
            $routes[] = new RouteDefinition(
                '/bench/' . $i,
                ['GET'],
                'bench.handler',
                'process',
                [],
                'bench.route.' . $i
            );
        }

        return $routes;
    }
}

final class BenchHandlerMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }
}

/**
 * @param list<float> $samples
 *
 * @return array{median: float, avg: float}
 */
function summarize(array $samples): array
{
    sort($samples);
    $count = count($samples);
    if (0 === $count) {
        return ['median' => 0.0, 'avg' => 0.0];
    }

    $middle = (int) floor($count / 2);
    $median = 0 === $count % 2
        ? ($samples[$middle - 1] + $samples[$middle]) / 2
        : $samples[$middle];

    return [
        'median' => round($median, 4),
        'avg' => round(array_sum($samples) / $count, 4),
    ];
}

/**
 * @return array{elapsed_ms: float, peak_kb: float, usage_delta_kb: float}
 */
function runProvider(
    int $routeCount,
    bool $cacheEnabled,
    string $cacheFile
): array
{
    $cacheConfig = [
        'enabled' => $cacheEnabled,
        'file' => $cacheFile,
    ];

    $config = [
        'routing_attributes' => [
            'classes' => ['Bench\\Virtual\\Handler'],
            'cache' => $cacheConfig,
        ],
    ];

    $container = new ThresholdBenchmarkContainer([
        'config' => $config,
        AttributeRouteExtractorInterface::class => new SyntheticExtractor($routeCount),
        'bench.handler' => new BenchHandlerMiddleware(),
    ]);

    $provider = (new AttributeRouteProviderFactory())($container);
    $collector = new ThresholdBenchmarkCollector();

    gc_collect_cycles();
    $usageBefore = memory_get_usage();
    if (function_exists('memory_reset_peak_usage')) {
        memory_reset_peak_usage();
    }

    $start = hrtime(true);
    $provider->registerRoutes($collector);
    $elapsedMs = (hrtime(true) - $start) / 1_000_000;
    $usageDeltaKb = max(memory_get_usage() - $usageBefore, 0) / 1024;
    $peakKb = max(memory_get_peak_usage() - $usageBefore, 0) / 1024;

    return [
        'elapsed_ms' => round($elapsedMs, 4),
        'peak_kb' => round($peakKb, 4),
        'usage_delta_kb' => round($usageDeltaKb, 4),
    ];
}

/**
 * @return array{
 *     median_ms: float,
 *     avg_ms: float,
 *     median_peak_kb: float,
 *     avg_peak_kb: float,
 *     median_usage_delta_kb: float,
 *     avg_usage_delta_kb: float
 * }
 */
function runScenario(
    int $routeCount,
    bool $cacheEnabled,
    string $cacheFile,
    int $iterations
): array
{
    $durations = [];
    $peaks = [];
    $usageDeltas = [];
    for ($i = 0; $i < $iterations; $i++) {
        $sample = runProvider(
            $routeCount,
            $cacheEnabled,
            $cacheFile
        );
        $durations[] = $sample['elapsed_ms'];
        $peaks[] = $sample['peak_kb'];
        $usageDeltas[] = $sample['usage_delta_kb'];
    }

    $durationSummary = summarize($durations);
    $peakSummary = summarize($peaks);
    $usageDeltaSummary = summarize($usageDeltas);

    return [
        'median_ms' => $durationSummary['median'],
        'avg_ms' => $durationSummary['avg'],
        'median_peak_kb' => $peakSummary['median'],
        'avg_peak_kb' => $peakSummary['avg'],
        'median_usage_delta_kb' => $usageDeltaSummary['median'],
        'avg_usage_delta_kb' => $usageDeltaSummary['avg'],
    ];
}

$routeCounts = [10, 25, 50, 100, 200, 400, 800, 1600, 2400, 3200, 4800, 6400, 9600, 12800];
$iterations = 20;
$cacheScenarios = [
    [
        'label' => 'compiled',
        'file_suffix' => 'compiled.cache.php',
    ],
];
$rows = [];
$firstCacheWin = [];
foreach ($cacheScenarios as $scenario) {
    $firstCacheWin[$scenario['label']] = null;
}

foreach ($routeCounts as $routeCount) {
    $cacheFileBase = sys_get_temp_dir() . '/mezzio-routing-attributes-threshold-' . $routeCount . '-' . uniqid('', true);
    $noCacheFile = $cacheFileBase . '-no-cache.php';

    if (is_file($noCacheFile)) {
        unlink($noCacheFile);
    }

    $noCache = runScenario($routeCount, false, $noCacheFile, $iterations);

    if (is_file($noCacheFile)) {
        unlink($noCacheFile);
    }

    $backendMetrics = [];
    foreach ($cacheScenarios as $scenario) {
        $label = $scenario['label'];
        $cacheFile = $cacheFileBase . '-' . $scenario['file_suffix'];
        if (is_file($cacheFile)) {
            unlink($cacheFile);
        }

        // Warm cache once before measuring warm hit.
        runProvider(
            $routeCount,
            true,
            $cacheFile
        );
        $cacheHit = runScenario(
            $routeCount,
            true,
            $cacheFile,
            $iterations
        );

        if (is_file($cacheFile)) {
            unlink($cacheFile);
        }

        $speedup = $noCache['median_ms'] > 0.0
            ? (($noCache['median_ms'] - $cacheHit['median_ms']) / $noCache['median_ms']) * 100
            : 0.0;

        if (null === $firstCacheWin[$label] && $cacheHit['median_ms'] <= $noCache['median_ms']) {
            $firstCacheWin[$label] = $routeCount;
        }

        $backendMetrics[$label] = [
            'cache_hit' => $cacheHit,
            'speedup_percent' => round($speedup, 2),
        ];
    }

    $rows[] = [
        'route_count' => $routeCount,
        'no_cache' => $noCache,
        'backend_metrics' => $backendMetrics,
    ];
}

echo "# Route Cache Threshold Benchmark\n\n";
echo sprintf("- PHP: `%s`\n", PHP_VERSION);
echo sprintf("- Iterations per point: `%d`\n", $iterations);
echo "- Interpretation: positive backend `speedup %` means warm cache hit is faster than no-cache.\n\n";
echo "- `usage delta KB` is non-peak live memory change (`memory_get_usage()` after - before route registration).\n\n";
echo "- Scenarios: `compiled`.\n\n";

$header = '| Routes | no-cache median ms';
foreach ($cacheScenarios as $scenario) {
    $label = $scenario['label'];
    $header .= ' | ' . $label . ' median ms';
    $header .= ' | ' . $label . ' speedup %';
}
$header .= ' | no-cache median peak KB';
foreach ($cacheScenarios as $scenario) {
    $label = $scenario['label'];
    $header .= ' | ' . $label . ' median peak KB';
}
$header .= ' | no-cache median usage delta KB';
foreach ($cacheScenarios as $scenario) {
    $label = $scenario['label'];
    $header .= ' | ' . $label . ' median usage delta KB';
}
$header .= " |\n";

$separator = '|---:|---:';
foreach ($cacheScenarios as $scenario) {
    $separator .= '|---:|---:';
}
$separator .= '|---:';
foreach ($cacheScenarios as $scenario) {
    $separator .= '|---:';
}
$separator .= '|---:';
foreach ($cacheScenarios as $scenario) {
    $separator .= '|---:';
}
$separator .= "|\n";

echo $header;
echo $separator;

foreach ($rows as $row) {
    $line = sprintf(
        '| %d | %s',
        $row['route_count'],
        number_format((float) $row['no_cache']['median_ms'], 4, '.', '')
    );

    foreach ($cacheScenarios as $scenario) {
        $metrics = $row['backend_metrics'][$scenario['label']];
        $line .= sprintf(
            ' | %s | %s',
            number_format((float) $metrics['cache_hit']['median_ms'], 4, '.', ''),
            number_format((float) $metrics['speedup_percent'], 2, '.', '')
        );
    }

    $line .= sprintf(' | %s', number_format((float) $row['no_cache']['median_peak_kb'], 4, '.', ''));
    foreach ($cacheScenarios as $scenario) {
        $metrics = $row['backend_metrics'][$scenario['label']];
        $line .= sprintf(' | %s', number_format((float) $metrics['cache_hit']['median_peak_kb'], 4, '.', ''));
    }
    $line .= sprintf(' | %s', number_format((float) $row['no_cache']['median_usage_delta_kb'], 4, '.', ''));
    foreach ($cacheScenarios as $scenario) {
        $metrics = $row['backend_metrics'][$scenario['label']];
        $line .= sprintf(' | %s', number_format((float) $metrics['cache_hit']['median_usage_delta_kb'], 4, '.', ''));
    }

    $line .= " |\n";
    echo $line;
}

echo "\n";
foreach ($cacheScenarios as $scenario) {
    $label = $scenario['label'];
    if (null !== $firstCacheWin[$label]) {
        echo sprintf("- First measured cache-win point (%s): `%d` routes.\n", $label, $firstCacheWin[$label]);

        continue;
    }

    echo sprintf("- No cache-win point found for `%s` in tested range.\n", $label);
}
