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

require dirname(__DIR__) . '/vendor/autoload.php';

final class ThresholdBenchmarkContainer implements ContainerInterface
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

final class BenchHandlerMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }
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
 * @param list<class-string<MiddlewareInterface>> $classNames
 */
function writeClassManifest(string $manifestFile, array $classNames): void
{
    $manifest = json_encode($classNames, JSON_THROW_ON_ERROR);
    if (false === file_put_contents($manifestFile, $manifest, LOCK_EX)) {
        throw new RuntimeException('Unable to write route benchmark class manifest.');
    }

    if (! chmod($manifestFile, 0o600)) {
        throw new RuntimeException('Unable to secure route benchmark class manifest.');
    }
}

/**
 * @return list<class-string<MiddlewareInterface>>
 */
function readClassManifest(string $manifestFile): array
{
    $manifest = file_get_contents($manifestFile);
    if (false === $manifest) {
        throw new RuntimeException('Unable to read route benchmark class manifest.');
    }

    $classNames = json_decode($manifest, true, flags: JSON_THROW_ON_ERROR);
    if (! is_array($classNames) || ! array_is_list($classNames)) {
        throw new RuntimeException('Route benchmark class manifest must contain a class-name list.');
    }

    foreach ($classNames as $className) {
        if (! is_string($className)) {
            throw new RuntimeException('Route benchmark class manifest contains an invalid class name.');
        }
    }

    /** @var list<class-string<MiddlewareInterface>> $classNames */
    return $classNames;
}

/**
 * @return list<class-string<MiddlewareInterface>>
 */
function createRouteCorpus(int $routeCount, string $sourceFile, string $profile): array
{
    $classRoutes = match ($profile) {
        'shared' => [$routeCount],
        'unique' => array_fill(0, $routeCount, 1),
        'mixed' => array_merge([(int) ceil($routeCount / 2)], array_fill(0, (int) floor($routeCount / 2), 1)),
        default => throw new RuntimeException(sprintf('Unsupported BENCHMARK_PROFILE "%s".', $profile)),
    };
    $classes = [];
    $route = 1;
    foreach ($classRoutes as $classIndex => $routesForClass) {
        $className = sprintf('RouteCorpus%s%s', ucfirst($profile), $classIndex + 1);
        $attributes = [];
        for ($i = 0; $i < $routesForClass; $i++, $route++) {
            $attributes[] = "#[\\Sirix\\Mezzio\\Routing\\Attributes\\Attribute\\Route('/bench/{$route}', ['GET'], 'bench.route.{$route}')]";
        }
        $classes[] = [
            'name' => $className,
            'attributes' => implode("\n", $attributes),
        ];
    }

    $source = <<<PHP
        namespace Bench\\Generated;

        use Psr\\Http\\Message\\ResponseInterface;
        use Psr\\Http\\Message\\ServerRequestInterface;
        use Psr\\Http\\Server\\MiddlewareInterface;
        use Psr\\Http\\Server\\RequestHandlerInterface;

        %s
        final class %s implements MiddlewareInterface
        {
            public function process(ServerRequestInterface \$request, RequestHandlerInterface \$handler): ResponseInterface
            {
                return \$handler->handle(\$request);
            }
        }
        PHP;
    $definitions = array_map(
        static fn (array $class): string => sprintf($source, $class['attributes'], $class['name']),
        $classes
    );
    $fileContents = "<?php\n\ndeclare(strict_types=1);\n\n" . implode("\n", $definitions);
    if (false === file_put_contents($sourceFile, $fileContents)) {
        throw new RuntimeException('Unable to create route benchmark corpus.');
    }

    /** @var list<class-string<MiddlewareInterface>> $classNames */
    $classNames = array_map(static fn (array $class): string => 'Bench\\Generated\\' . $class['name'], $classes);

    return $classNames;
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
 * @return array{elapsed_ms: float, peak_kb: float, usage_delta_kb: float, route_calls: int}
 */
function runProvider(
    bool $cacheEnabled,
    string $cacheFile,
    string $sourceFile,
    array $classNames,
    bool $loadCorpus,
    int $expectedRouteCount
): array
{
    $cacheConfig = [
        'enabled' => $cacheEnabled,
        'file' => $cacheFile,
    ];

    $config = [
        'routing_attributes' => [
            'classes' => $classNames,
            'cache' => $cacheConfig,
        ],
    ];

    $container = new ThresholdBenchmarkContainer([
        'config' => $config,
        RoutingAttributesConfig::class => RoutingAttributesConfig::fromRootConfig($config),
        AttributeRouteExtractorInterface::class => createBenchmarkExtractor(),
        ServiceMiddlewareResolver::class => new ServiceMiddlewareResolver(),
        ...array_fill_keys($classNames, new BenchHandlerMiddleware()),
    ]);
    $container->set(RouteRegistrarCacheInterface::class, (new CompiledRouteRegistrarCacheFactory())($container));
    $container->set(DuplicateRouteResolver::class, (new DuplicateRouteResolverFactory())($container));
    $container->set(DiscoveredClassesResolverInterface::class, (new DiscoveryClassMapResolverFactory())($container));
    $container->set(MiddlewarePipelineFactory::class, (new MiddlewarePipelineFactoryFactory())($container));

    $provider = (new AttributeRouteProviderFactory())($container);
    $collector = new ThresholdBenchmarkCollector();

    gc_collect_cycles();
    $usageBefore = memory_get_usage();
    if (function_exists('memory_reset_peak_usage')) {
        memory_reset_peak_usage();
    }

    $start = hrtime(true);
    if ($loadCorpus) {
        require $sourceFile;
    }
    $provider->registerRoutes($collector);
    $elapsedMs = (hrtime(true) - $start) / 1_000_000;
    $usageDeltaKb = max(memory_get_usage() - $usageBefore, 0) / 1024;
    $peakKb = max(memory_get_peak_usage() - $usageBefore, 0) / 1024;
    if ($collector->routeCalls !== $expectedRouteCount) {
        throw new RuntimeException(sprintf(
            'Expected %d registered routes, got %d.',
            $expectedRouteCount,
            $collector->routeCalls
        ));
    }

    return [
        'elapsed_ms' => round($elapsedMs, 4),
        'peak_kb' => round($peakKb, 4),
        'usage_delta_kb' => round($usageDeltaKb, 4),
        'route_calls' => $collector->routeCalls,
    ];
}

/**
 * @return array{elapsed_ms: float, peak_kb: float, usage_delta_kb: float, route_calls: int}
 */
function runProviderInFreshProcess(
    bool $cacheEnabled,
    string $cacheFile,
    string $sourceFile,
    string $classManifestFile,
    int $expectedRouteCount,
    bool $loadCorpus
): array
{
    $process = proc_open(
        [
            PHP_BINARY,
            __FILE__,
            '--sample',
            $cacheEnabled ? '1' : '0',
            $cacheFile,
            $sourceFile,
            $classManifestFile,
            (string) $expectedRouteCount,
            $loadCorpus ? '1' : '0',
        ],
        [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes
    );
    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start route cache threshold sample process.');
    }

    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if (0 !== $exitCode) {
        throw new RuntimeException('Route cache threshold sample process failed: ' . $errors);
    }

    /** @var array{elapsed_ms: float, peak_kb: float, usage_delta_kb: float, route_calls: int} $sample */
    $sample = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

    return $sample;
}

if ('--sample' === ($argv[1] ?? null)) {
    echo json_encode(
        runProvider('1' === $argv[2], $argv[3], $argv[4], readClassManifest($argv[5]), '1' === $argv[7], (int) $argv[6]),
        JSON_THROW_ON_ERROR
    );
    exit(0);
}

/**
 * @param callable(): array{elapsed_ms: float, peak_kb: float, usage_delta_kb: float, route_calls: int} $iteration
 *
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
    int $iterations,
    callable $iteration
): array
{
    $durations = [];
    $peaks = [];
    $usageDeltas = [];
    for ($i = 0; $i < $iterations; $i++) {
        $sample = $iteration();
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

$routeCounts = array_map(
    static fn (string $routeCount): int => (int) $routeCount,
    explode(',', getenv('BENCHMARK_ROUTE_COUNTS') ?: '10,16,17,25,50,100,200,400,800,1600,2400,3200,4800,6400,9600,12800')
);
$iterations = max(1, (int) (getenv('BENCHMARK_ITERATIONS') ?: 20));
$profile = getenv('BENCHMARK_PROFILE') ?: 'mixed';
if (! in_array($profile, ['shared', 'unique', 'mixed'], true)) {
    throw new RuntimeException('BENCHMARK_PROFILE must be shared, unique, or mixed.');
}
$rows = [];
$firstCacheWin = null;
$temporaryDirectory = createTemporaryDirectory();
register_shutdown_function(static function() use ($temporaryDirectory): void {
    removeTemporaryDirectory($temporaryDirectory);
});

foreach ($routeCounts as $routeCount) {
    $cacheFileBase = $temporaryDirectory . '/routes-' . $routeCount;
    $noCacheFile = $cacheFileBase . '-no-cache.php';
    $sourceFile  = $cacheFileBase . '-corpus.php';
    $manifestFile = $cacheFileBase . '-classes.json';
    $classNames  = createRouteCorpus($routeCount, $sourceFile, $profile);
    writeClassManifest($manifestFile, $classNames);

    if (is_file($noCacheFile)) {
        unlink($noCacheFile);
    }

    $noCache = runScenario(
        $iterations,
        static fn (): array => runProviderInFreshProcess(false, $noCacheFile, $sourceFile, $manifestFile, $routeCount, true)
    );

    if (is_file($noCacheFile)) {
        unlink($noCacheFile);
    }

    $cacheFile = $cacheFileBase . '-compiled.cache.php';
    if (is_file($cacheFile)) {
        unlink($cacheFile);
    }

    // Warm the production artifact in an isolated process before measuring cache hits.
    runProviderInFreshProcess(true, $cacheFile, $sourceFile, $manifestFile, $routeCount, true);
    $cacheHit = runScenario(
        $iterations,
        static fn (): array => runProviderInFreshProcess(true, $cacheFile, $sourceFile, $manifestFile, $routeCount, false)
    );

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

    if (is_file($sourceFile)) {
        unlink($sourceFile);
    }

    if (is_file($manifestFile)) {
        unlink($manifestFile);
    }
}

echo "# Route Cache Threshold Benchmark\n\n";
echo sprintf("- PHP: `%s`\n", PHP_VERSION);
echo sprintf("- Iterations per point: `%d`\n", $iterations);
echo sprintf("- Corpus profile: `%s`\n", $profile);
echo "- Interpretation: positive backend `speedup %` means warm cache hit is faster than no-cache.\n\n";
echo "- `usage delta KB` is non-peak live memory change (`memory_get_usage()` after - before route registration).\n\n";
echo "- Scenarios: `no-cache`, `compiled`.\n\n";

$header = '| Routes | no-cache median ms | compiled median ms | compiled speedup % | no-cache median peak KB | compiled median peak KB | no-cache median usage delta KB | compiled median usage delta KB |' . "\n";
$separator = '|---:|---:|---:|---:|---:|---:|---:|---:|' . "\n";

echo $header;
echo $separator;

foreach ($rows as $row) {
    $line = sprintf(
        '| %d | %s',
        $row['route_count'],
        number_format((float) $row['no_cache']['median_ms'], 4, '.', '')
    );

    $line .= sprintf(
        ' | %s | %s | %s | %s | %s | %s',
        number_format((float) $row['cache_hit']['median_ms'], 4, '.', ''),
        number_format((float) $row['speedup_percent'], 2, '.', ''),
        number_format((float) $row['no_cache']['median_peak_kb'], 4, '.', ''),
        number_format((float) $row['cache_hit']['median_peak_kb'], 4, '.', ''),
        number_format((float) $row['no_cache']['median_usage_delta_kb'], 4, '.', ''),
        number_format((float) $row['cache_hit']['median_usage_delta_kb'], 4, '.', '')
    );

    $line .= " |\n";
    echo $line;
}

echo "\n";
if (null !== $firstCacheWin) {
    echo sprintf("- First measured cache-win point: `%d` routes.\n", $firstCacheWin);

    exit(0);
}
echo "- No cache-win point found in tested range.\n";
