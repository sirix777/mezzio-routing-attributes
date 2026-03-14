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
 * @return array{elapsed_ms: float, peak_kb: float}
 */
function runProvider(int $routeCount, bool $cacheEnabled, string $cacheFile): array
{
    $config = [
        'routing_attributes' => [
            'classes' => ['Bench\\Virtual\\Handler'],
            'cache' => [
                'enabled' => $cacheEnabled,
                'file' => $cacheFile,
                'strict' => false,
                'write_fail_strategy' => 'ignore',
            ],
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
    $memoryBefore = memory_get_usage();
    if (function_exists('memory_reset_peak_usage')) {
        memory_reset_peak_usage();
    }

    $start = hrtime(true);
    $provider->registerRoutes($collector);
    $elapsedMs = (hrtime(true) - $start) / 1_000_000;
    $peakKb = max(memory_get_peak_usage() - $memoryBefore, 0) / 1024;

    return [
        'elapsed_ms' => round($elapsedMs, 4),
        'peak_kb' => round($peakKb, 4),
    ];
}

/**
 * @return array{median_ms: float, avg_ms: float, median_peak_kb: float, avg_peak_kb: float}
 */
function runScenario(int $routeCount, bool $cacheEnabled, string $cacheFile, int $iterations): array
{
    $durations = [];
    $peaks = [];
    for ($i = 0; $i < $iterations; $i++) {
        $sample = runProvider($routeCount, $cacheEnabled, $cacheFile);
        $durations[] = $sample['elapsed_ms'];
        $peaks[] = $sample['peak_kb'];
    }

    $durationSummary = summarize($durations);
    $peakSummary = summarize($peaks);

    return [
        'median_ms' => $durationSummary['median'],
        'avg_ms' => $durationSummary['avg'],
        'median_peak_kb' => $peakSummary['median'],
        'avg_peak_kb' => $peakSummary['avg'],
    ];
}

$routeCounts = [10, 25, 50, 100, 200, 400, 800, 1600, 2400, 3200, 4800, 6400];
$iterations = 20;
$rows = [];
$firstCacheWin = null;

foreach ($routeCounts as $routeCount) {
    $cacheFile = sys_get_temp_dir() . '/mezzio-routing-attributes-threshold-' . $routeCount . '-' . uniqid('', true) . '.php';

    if (is_file($cacheFile)) {
        unlink($cacheFile);
    }

    $noCache = runScenario($routeCount, false, $cacheFile, $iterations);

    if (is_file($cacheFile)) {
        unlink($cacheFile);
    }

    // Warm cache once before measuring warm hit.
    runProvider($routeCount, true, $cacheFile);
    $cacheHit = runScenario($routeCount, true, $cacheFile, $iterations);

    if (is_file($cacheFile)) {
        unlink($cacheFile);
    }

    $speedup = $noCache['median_ms'] > 0.0
        ? (($noCache['median_ms'] - $cacheHit['median_ms']) / $noCache['median_ms']) * 100
        : 0.0;

    if (null === $firstCacheWin && $cacheHit['median_ms'] <= $noCache['median_ms']) {
        $firstCacheWin = $routeCount;
    }

    $rows[] = [
        'route_count' => $routeCount,
        'no_cache' => $noCache,
        'cache_hit' => $cacheHit,
        'speedup_percent' => round($speedup, 2),
    ];
}

echo "# Route Cache Threshold Benchmark\n\n";
echo sprintf("- PHP: `%s`\n", PHP_VERSION);
echo sprintf("- Iterations per point: `%d`\n", $iterations);
echo "- Interpretation: positive `cache speedup %` means warm cache hit is faster than no-cache.\n\n";
echo "| Routes | no-cache median ms | cache-hit median ms | cache speedup % | no-cache median peak KB | cache-hit median peak KB |\n";
echo "|---:|---:|---:|---:|---:|---:|\n";

foreach ($rows as $row) {
    echo sprintf(
        "| %d | %s | %s | %s | %s | %s |\n",
        $row['route_count'],
        number_format((float) $row['no_cache']['median_ms'], 4, '.', ''),
        number_format((float) $row['cache_hit']['median_ms'], 4, '.', ''),
        number_format((float) $row['speedup_percent'], 2, '.', ''),
        number_format((float) $row['no_cache']['median_peak_kb'], 4, '.', ''),
        number_format((float) $row['cache_hit']['median_peak_kb'], 4, '.', '')
    );
}

echo "\n";
if (null !== $firstCacheWin) {
    echo sprintf("- First measured cache-win point: `%d` routes.\n", $firstCacheWin);
} else {
    echo "- No cache-win point found in tested range.\n";
}
