<?php

declare(strict_types=1);

use Mezzio\Router\Route;
use Mezzio\Router\RouteCollectorInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Sirix\Mezzio\Routing\Attributes\AttributeRouteProviderFactory;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteRegistrarCacheInterface;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveredClassesResolverInterface;
use Sirix\Mezzio\Routing\Attributes\DuplicateRouteResolver;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractor;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;
use Sirix\Mezzio\Routing\Attributes\Extractor\ClassEligibilityValidator;
use Sirix\Mezzio\Routing\Attributes\Extractor\MethodSignatureValidator;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteAttributeReader;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteDataNormalizer;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteDefinitionBuilder;
use Sirix\Mezzio\Routing\Attributes\Factory\CompiledRouteRegistrarCacheFactory;
use Sirix\Mezzio\Routing\Attributes\Factory\DiscoveryClassMapResolverFactory;
use Sirix\Mezzio\Routing\Attributes\Factory\DuplicateRouteResolverFactory;
use Sirix\Mezzio\Routing\Attributes\Factory\MiddlewarePipelineFactoryFactory;
use Sirix\Mezzio\Routing\Attributes\MiddlewarePipelineFactory;
use Sirix\Mezzio\Routing\Attributes\ServiceMiddlewareResolver;
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

    /**
     * @param array<string, mixed> $services
     */
    public function __construct(array $services)
    {
        $this->services = $services;
    }

    public function get(string $id): mixed
    {
        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }

    public function set(string $id, mixed $service): void
    {
        $this->services[$id] = $service;
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

function createTemporaryDirectory(): string
{
    $directory = sys_get_temp_dir() . '/mezzio-routing-attributes-benchmark-' . bin2hex(random_bytes(16));
    if (! mkdir($directory, 0o700)) {
        throw new RuntimeException('Unable to create private route benchmark directory.');
    }

    return $directory;
}

function removeTemporaryDirectory(string $directory): void
{
    foreach (glob($directory . '/*') ?: [] as $file) {
        if (is_file($file) || is_link($file)) {
            unlink($file);
        }
    }

    rmdir($directory);
}

/**
 * @param callable(): array{
 *     elapsed_ms: float,
 *     peak_memory_usage_kb: float,
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
 *     avg_route_calls: float
 * }
 */
function runScenario(callable $iteration, int $iterations): array
{
    $durations = [];
    $peakMemoryUsages = [];
    $routeCalls = 0;

    for ($i = 0; $i < $iterations; $i++) {
        $sample = $iteration();
        $durations[] = $sample['elapsed_ms'];
        $peakMemoryUsages[] = $sample['peak_memory_usage_kb'];
        $routeCalls += $sample['route_calls'];
    }

    $summary = summarizeSamples($durations);
    $peakMemorySummary = summarizeSamples($peakMemoryUsages);
    $summary['avg_peak_memory_usage_kb'] = $peakMemorySummary['avg_ms'];
    $summary['median_peak_memory_usage_kb'] = $peakMemorySummary['median_ms'];
    $summary['max_peak_memory_usage_kb'] = $peakMemorySummary['max_ms'];
    $summary['avg_route_calls'] = round($routeCalls / $iterations, 2);

    return $summary;
}

function createBenchmarkExtractor(): AttributeRouteExtractor
{
    $attributeReader = new RouteAttributeReader();

    return new AttributeRouteExtractor(
        new ClassEligibilityValidator(false),
        $attributeReader,
        new RouteDefinitionBuilder(
            $attributeReader,
            new MethodSignatureValidator(),
            new RouteDataNormalizer()
        )
    );
}

function runProvider(array $config): array
{
    $extractor = createBenchmarkExtractor();
    $container = new BenchmarkContainer([
        'config' => $config,
        RoutingAttributesConfig::class => RoutingAttributesConfig::fromRootConfig($config),
        AttributeRouteExtractorInterface::class => $extractor,
        ServiceMiddlewareResolver::class => new ServiceMiddlewareResolver(),
        PingHandler::class => new PingHandler(),
        PingRequestHandler::class => new PingRequestHandler(),
        StackedHandler::class => new StackedHandler(),
        StackFirstMiddleware::class => new StackFirstMiddleware(),
        StackSecondMiddleware::class => new StackSecondMiddleware(),
    ]);
    $container->set(RouteRegistrarCacheInterface::class, (new CompiledRouteRegistrarCacheFactory())($container));
    $container->set(DuplicateRouteResolver::class, (new DuplicateRouteResolverFactory())($container));
    $container->set(DiscoveredClassesResolverInterface::class, (new DiscoveryClassMapResolverFactory())($container));
    $container->set(MiddlewarePipelineFactory::class, (new MiddlewarePipelineFactoryFactory())($container));

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
        'route_calls' => $collector->routeCalls,
    ];
}

/**
 * @return array{elapsed_ms: float, peak_memory_usage_kb: float, route_calls: int}
 */
function runProviderInFreshProcess(array $config): array
{
    $encodedConfig = base64_encode(json_encode($config, JSON_THROW_ON_ERROR));
    $process       = proc_open(
        [PHP_BINARY, __FILE__, '--sample', $encodedConfig],
        [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes
    );
    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start route provider benchmark sample process.');
    }

    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if (0 !== $exitCode) {
        throw new RuntimeException('Route provider benchmark sample process failed: ' . $errors);
    }

    /** @var array{elapsed_ms: float, peak_memory_usage_kb: float, route_calls: int} $sample */
    $sample = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

    return $sample;
}

if ('--sample' === ($argv[1] ?? null)) {
    /** @var array<string, mixed> $config */
    $config = json_decode(base64_decode($argv[2], true) ?: '', true, flags: JSON_THROW_ON_ERROR);
    echo json_encode(runProvider($config), JSON_THROW_ON_ERROR);
    exit(0);
}

$iterations = max(1, (int) (getenv('BENCHMARK_ITERATIONS') ?: 100));
$temporaryDirectory = createTemporaryDirectory();
register_shutdown_function(static function() use ($temporaryDirectory): void {
    removeTemporaryDirectory($temporaryDirectory);
});
$tempPrefix = $temporaryDirectory . '/routes';
$manualRouteCacheFile = $tempPrefix . '-manual-routes.php';
$discoveryTokenRouteCacheFile = $tempPrefix . '-discovery-token-routes.php';
$discoveryPsr4RouteCacheFile = $tempPrefix . '-discovery-psr4-routes.php';
$discoveryPath = dirname(__DIR__) . '/test/Extractor/Fixture';

$manualConfig = [
    'routing_attributes' => [
        'classes' => [PingHandler::class],
        'cache' => [
            'enabled' => true,
            'file' => $manualRouteCacheFile,
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

if (file_exists($manualRouteCacheFile)) {
    unlink($manualRouteCacheFile);
}
runProvider($manualConfig);

$warmManual = runScenario(static fn (): array => runProviderInFreshProcess($manualConfig), $iterations);

$noCacheManual = runScenario(static fn (): array => runProviderInFreshProcess($manualNoCacheConfig), $iterations);

if (file_exists($manualRouteCacheFile)) {
    unlink($manualRouteCacheFile);
}
$coldManual = runScenario(static function() use ($manualConfig, $manualRouteCacheFile): array {
    if (file_exists($manualRouteCacheFile)) {
        unlink($manualRouteCacheFile);
    }

    return runProviderInFreshProcess($manualConfig);
}, $iterations);

$discoveryBaseConfig = [
    'routing_attributes' => [
        'classes' => [],
        'cache' => [
            'enabled' => true,
            'file' => $discoveryTokenRouteCacheFile,
        ],
        'discovery' => [
            'enabled' => true,
            'paths' => [$discoveryPath],
            'strategy' => 'token',
        ],
    ],
];

if (file_exists($discoveryTokenRouteCacheFile)) {
    unlink($discoveryTokenRouteCacheFile);
}
runProvider($discoveryBaseConfig);
$warmDiscoveryToken = runScenario(static fn (): array => runProviderInFreshProcess($discoveryBaseConfig), $iterations);

$discoveryPsr4Config = $discoveryBaseConfig;
$discoveryPsr4Config['routing_attributes']['cache']['file'] = $discoveryPsr4RouteCacheFile;
$discoveryPsr4Config['routing_attributes']['discovery']['strategy'] = 'psr4';
$discoveryPsr4Config['routing_attributes']['discovery']['psr4'] = [
    'mappings' => [
        $discoveryPath => 'SirixTest\\Mezzio\\Routing\\Attributes\\Extractor\\Fixture\\',
    ],
    'fallback_to_token' => true,
];

if (file_exists($discoveryPsr4RouteCacheFile)) {
    unlink($discoveryPsr4RouteCacheFile);
}
runProvider($discoveryPsr4Config);
$warmDiscoveryPsr4 = runScenario(static fn (): array => runProviderInFreshProcess($discoveryPsr4Config), $iterations);

if (file_exists($manualRouteCacheFile)) {
    unlink($manualRouteCacheFile);
}
if (file_exists($discoveryTokenRouteCacheFile)) {
    unlink($discoveryTokenRouteCacheFile);
}
if (file_exists($discoveryPsr4RouteCacheFile)) {
    unlink($discoveryPsr4RouteCacheFile);
}

$report = [
    'php_version' => PHP_VERSION,
    'timestamp' => date('c'),
    'iterations' => $iterations,
    'measurement' => [
        'warm_cache_mode' => 'fresh_process',
        'artifact_load_included' => true,
    ],
    'scenario_notes' => [
        'warm_cache_hit_manual' => 'Fresh-process cache hit with explicit class list, including artifact require/load.',
        'no_cache_manual' => 'Manual class list without route cache. Lower-bound reference for registration overhead.',
        'cold_cache_rebuild_manual' => 'Route cache cold rebuild from explicit class list. Captures extraction/write cost.',
        'warm_cache_hit_discovery_token' => 'Fresh-process cache hit with token discovery configuration; discovery is skipped on hit.',
        'warm_cache_hit_discovery_psr4' => 'Fresh-process cache hit with PSR-4 discovery configuration; discovery is skipped on hit.',
    ],
    'scenarios' => [
        'warm_cache_hit_manual' => $warmManual,
        'no_cache_manual' => $noCacheManual,
        'cold_cache_rebuild_manual' => $coldManual,
        'warm_cache_hit_discovery_token' => $warmDiscoveryToken,
        'warm_cache_hit_discovery_psr4' => $warmDiscoveryPsr4,
    ],
    'budget' => [
        'cache_hit_regression_max_percent' => 5.0,
    ],
];

$baselineFile = dirname(__DIR__) . '/benchmarks/baseline.json';
$baselineCompatible = false;
if (file_exists($baselineFile)) {
    $decoded = json_decode((string) file_get_contents($baselineFile), true);
    if (
        is_array($decoded)
        && 'fresh_process' === ($decoded['measurement']['warm_cache_mode'] ?? null)
        && true === ($decoded['measurement']['artifact_load_included'] ?? null)
        && isset($decoded['scenarios']['warm_cache_hit_manual']['median_ms'])
    ) {
        $baselineCompatible = true;
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
echo "| Scenario | median ms | avg ms | min ms | max ms | median peak KB | avg peak KB | max peak KB | avg routes |\n";
echo "|---|---:|---:|---:|---:|---:|---:|---:|---:|\n";

foreach ($report['scenarios'] as $name => $metrics) {
    $scenarioName = $name;
    echo sprintf(
        "| `%s` | %s | %s | %s | %s | %s | %s | %s | %s |\n",
        $scenarioName,
        number_format((float) $metrics['median_ms'], 4, '.', ''),
        number_format((float) $metrics['avg_ms'], 4, '.', ''),
        number_format((float) $metrics['min_ms'], 4, '.', ''),
        number_format((float) $metrics['max_ms'], 4, '.', ''),
        number_format((float) $metrics['median_peak_memory_usage_kb'], 4, '.', ''),
        number_format((float) $metrics['avg_peak_memory_usage_kb'], 4, '.', ''),
        number_format((float) $metrics['max_peak_memory_usage_kb'], 4, '.', ''),
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
    echo $baselineCompatible
        ? "- Compatible baseline contains no warm cache median; comparison skipped.\n"
        : "- Baseline predates fresh-process artifact loading; comparison skipped.\n";
}

echo "\nReport JSON: `benchmarks/report.json`\n";

if (isset($report['comparison']) && ! $report['comparison']['within_budget']) {
    exit(1);
}
