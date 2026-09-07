<?php

declare(strict_types=1);

use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ServerRequest;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\MiddlewarePipelineFactory;
use Sirix\Mezzio\Routing\Attributes\ServiceMiddlewareResolver;
use Sirix\Mezzio\Routing\Contracts\MiddlewareFactoryInterface;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;

require \dirname(__DIR__) . '/vendor/autoload.php';

final class PipelineBenchmarkState
{
    public int $middlewareCalls = 0;
    public int $handlerCalls    = 0;
    public int $containerGets   = 0;
    public int $factoryCalls    = 0;
    public bool $recordOrder    = false;

    /** @var list<int> */
    public array $order = [];
}

final class PipelineBenchmarkMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly PipelineBenchmarkState $state, private readonly int $index) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        ++$this->state->middlewareCalls;
        if ($this->state->recordOrder) {
            $this->state->order[] = $this->index;
        }

        return $handler->handle($request);
    }
}

final class PipelineBenchmarkContainer implements ContainerInterface, MiddlewareFactoryInterface
{
    public function __construct(private readonly PipelineBenchmarkState $state, private readonly RequestHandlerInterface $handler) {}

    public function has(string $id): bool
    {
        return 'factory' === $id || 'handler' === $id || 1 === \preg_match('/^middleware-[0-9]+$/D', $id);
    }

    public function get(string $id): mixed
    {
        ++$this->state->containerGets;

        return match (true) {
            'factory' === $id => $this,
            'handler' === $id => $this->handler,
            $this->has($id)   => new PipelineBenchmarkMiddleware($this->state, (int) \substr($id, 11)),
            default           => throw new RuntimeException('Unknown benchmark service: ' . $id),
        };
    }

    public function create(ContainerInterface $container, MiddlewareSpecification $specification): MiddlewareInterface
    {
        ++$this->state->factoryCalls;

        return new PipelineBenchmarkMiddleware($this->state, $specification->arguments['index']);
    }
}

final class PipelineBenchmarkHandler implements RequestHandlerInterface
{
    private readonly ResponseInterface $response;

    public function __construct(private readonly PipelineBenchmarkState $state, private readonly bool $useful)
    {
        $this->response = new Response(status: 204);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        ++$this->state->handlerCalls;
        if (! $this->useful) {
            return $this->response;
        }

        // A fixed illustrative CPU/JSON workload, not an application latency model.
        $items = [];
        for ($i = 1; $i <= 100; ++$i) {
            $items[] = [
                'id'          => $i,
                'total_cents' => $i * 125,
                'digest'      => \hash('sha256', 'item-' . $i),
            ];
        }

        return new JsonResponse([
            'items' => $items,
        ]);
    }
}

function pipelineBenchmarkInt(string $name, int $default, int $maximum): int
{
    $raw = \getenv($name);
    if (false === $raw) {
        return $default;
    }
    $value = \filter_var($raw, FILTER_VALIDATE_INT, [
        'options' => [
            'min_range' => 1,
            'max_range' => $maximum,
        ],
    ]);
    if (false === $value) {
        throw new InvalidArgumentException(\sprintf('%s must be an integer between 1 and %d.', $name, $maximum));
    }

    return $value;
}

function pipelineBenchmarkCheck(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException('Benchmark correctness check failed: ' . $message);
    }
}

/** @return array{elapsed_ns_per_call: float, retained_bytes: int, peak_bytes: int} */
function pipelineBenchmarkMeasure(Closure $call, int $repeats, ResponseInterface $expected): array
{
    // Warm autoload separately; reset peak for each measured interval (PHP >= 8.2).
    \gc_collect_cycles();
    \memory_reset_peak_usage();
    $before = \memory_get_usage();
    $start  = \hrtime(true);
    for ($i = 0; $i < $repeats; ++$i) {
        unset($response);
        $response = $call();
    }
    $elapsed  = \hrtime(true) - $start;
    $retained = \memory_get_usage() - $before;
    $peak     = \max(0, \memory_get_peak_usage() - $before);

    // Check the actual first/last measured response outside timing; counts are checked by the caller.
    \pipelineBenchmarkCheck($response->getStatusCode() === $expected->getStatusCode(), 'response status');
    \pipelineBenchmarkCheck((string) $response->getBody() === (string) $expected->getBody(), 'response body');

    return [
        'elapsed_ns_per_call' => $elapsed / $repeats,
        'retained_bytes'      => $retained,
        'peak_bytes'          => $peak,
    ];
}

/** @return array{first: array, warm: array} */
function pipelineBenchmarkSample(
    string $kind,
    int $count,
    bool $useful,
    int $repeats,
    int $warmup
): array {
    $state      = new PipelineBenchmarkState();
    $handler    = new PipelineBenchmarkHandler($state, $useful);
    $request    = new ServerRequest();
    $expected   = (new PipelineBenchmarkHandler(new PipelineBenchmarkState(), $useful))->handle($request);
    $downstream = new class implements RequestHandlerInterface {
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            throw new RuntimeException('Terminal benchmark handler unexpectedly delegated.');
        }
    };
    $entries = [];
    for ($i = 1; $i <= $count; ++$i) {
        $entries[] = 'specification' === $kind
            ? new MiddlewareSpecification('middleware-' . $i, 'factory', [
                'index' => $i,
            ])
            : 'middleware-' . $i;
    }
    $container = new PipelineBenchmarkContainer($state, $handler);
    $pipeline  = (new MiddlewarePipelineFactory($container, new ServiceMiddlewareResolver()))
        ->createFromSignature('handler', 'handle', $entries)
    ;
    \pipelineBenchmarkCheck(0 === $state->containerGets && 0 === $state->factoryCalls, 'resolution must be lazy');
    $call = 0 === $count
        ? static fn (): ResponseInterface => $handler->handle($request)
        : static fn (): ResponseInterface => $pipeline->process($request, $downstream);

    $first             = \pipelineBenchmarkMeasure($call, 1, $expected);
    $expectedGets      = 0 === $count ? 0 : $count + 1;
    $expectedFactories = 'specification' === $kind ? $count : 0;
    \pipelineBenchmarkCheck($state->containerGets === $expectedGets, 'first request container resolution count');
    \pipelineBenchmarkCheck($state->factoryCalls === $expectedFactories, 'first request factory count');

    $state->recordOrder = true;
    $call();
    \pipelineBenchmarkCheck($state->order === (0 === $count ? [] : \range(1, $count)), 'middleware order');
    $state->recordOrder = false;
    $state->order       = [];
    for ($i = 0; $i < $warmup; ++$i) {
        $call();
    }
    $warm          = \pipelineBenchmarkMeasure($call, $repeats, $expected);
    $expectedCalls = $warmup + $repeats + 2; // first + order check
    \pipelineBenchmarkCheck($state->handlerCalls === $expectedCalls, 'handler invocation count');
    \pipelineBenchmarkCheck($state->middlewareCalls === $expectedCalls * $count, 'middleware invocation count');
    \pipelineBenchmarkCheck($state->containerGets === $expectedGets, 'warm requests must not resolve services again');
    \pipelineBenchmarkCheck($state->factoryCalls === $expectedFactories, 'warm requests must not recreate specifications');

    return [
        'first' => $first,
        'warm'  => $warm,
    ];
}

/** @param list<float|int> $values */
function pipelineBenchmarkMedian(array $values): float
{
    \sort($values);
    $middle = \intdiv(\count($values), 2);

    return 0 === \count($values) % 2 ? ($values[$middle - 1] + $values[$middle]) / 2 : (float) $values[$middle];
}

try {
    if (\count($argv) > 2 || (isset($argv[1]) && '--json' !== $argv[1])) {
        throw new InvalidArgumentException('Usage: php benchmarks/pipeline-benchmark.php [--json]');
    }
    $samples = \pipelineBenchmarkInt('BENCHMARK_SAMPLES', 7, 100);
    $repeats = \pipelineBenchmarkInt('BENCHMARK_REPEATS', 5000, 100000);
    $warmup  = \pipelineBenchmarkInt('BENCHMARK_WARMUP', 100, 10000);
    $opcache = \function_exists('opcache_get_status') ? \opcache_get_status(false) : false;
    $report  = [
        'runtime'       => [
            'php'                => PHP_VERSION,
            'binary'             => PHP_BINARY,
            'sapi'               => PHP_SAPI,
            'os'                 => PHP_OS_FAMILY,
            'opcache_enable_cli' => \ini_get('opcache.enable_cli'),
            'opcache_active'     => false !== $opcache,
            'jit'                => \ini_get('opcache.jit'),
            'jit_buffer_size'    => \ini_get('opcache.jit_buffer_size'),
            'jit_status'         => false === $opcache ? null : ($opcache['jit'] ?? null),
            'xdebug_loaded'      => \extension_loaded('xdebug'),
            'pcov_loaded'        => \extension_loaded('pcov'),
        ],
        'configuration' => [
            'samples'           => $samples,
            'repeats'           => $repeats,
            'warmup'            => $warmup,
            'middleware_counts' => [1, 5, 10, 20],
        ],
        'methodology'   => [
            'Fresh pipeline per sample; first is one lazy call, warm is a repeated batch after resolution and warmup.',
            'All samples share one process; first-call measurement excludes construction and process/autoload startup.',
            'N middleware entries plus the terminal handler adapter; direct baselines bypass the pipeline.',
            'No-op middleware increments a counter; handler increments a counter. Order recording and checks are outside timing.',
            'Useful handler builds 100 records, computes 100 SHA-256 digests and emits a JsonResponse; illustrative CPU/JSON work only.',
            'Memory is PHP live retained delta and reset peak above interval start, not total allocated bytes, allocation count or RSS.',
            'The last measured response remains live during memory sampling and is validated after timing.',
            'Released wrappers can yield zero retained bytes; peak includes response/workload allocations and cannot isolate wrappers.',
            'Raw samples are retained; no percentage CI threshold or guaranteed HTTP latency improvement.',
        ],
        'results'       => [],
    ];
    // Prime all classes and both resolution paths outside all reported samples.
    \pipelineBenchmarkSample('service', 1, true, 1, 1);
    \pipelineBenchmarkSample('specification', 1, false, 1, 1);
    $scenarios = [];
    foreach ([false, true] as $useful) {
        $scenarios[] = ['direct', 0, $useful];
        foreach (['service', 'specification'] as $kind) {
            foreach ([1, 5, 10, 20] as $count) {
                $scenarios[] = [$kind, $count, $useful];
            }
        }
    }

    // Alternate scenario order between rounds to reduce systematic time/order bias.
    for ($sample = 0; $sample < $samples; ++$sample) {
        foreach (0 === $sample % 2 ? $scenarios : \array_reverse($scenarios) as [$kind, $count, $useful]) {
            $key = $kind . '/' . $count . '/' . ($useful ? 'cpu-json' : 'noop');
            if ('debug' === \getenv('LOG_LEVEL')) {
                \fwrite(STDERR, \json_encode([
                    'event'    => 'pipeline_sample',
                    'scenario' => $key,
                    'sample'   => $sample + 1,
                ], JSON_THROW_ON_ERROR) . "\n");
            }
            $report['results'][$key]['samples'][] = \pipelineBenchmarkSample($kind, $count, $useful, $repeats, $warmup);
        }
    }

    foreach ($report['results'] as &$result) {
        foreach (['first', 'warm'] as $phase) {
            foreach (['elapsed_ns_per_call', 'retained_bytes', 'peak_bytes'] as $metric) {
                $values                             = \array_column(\array_column($result['samples'], $phase), $metric);
                $result['summary'][$phase][$metric] = [
                    'min'    => \min($values),
                    'median' => \pipelineBenchmarkMedian($values),
                    'max'    => \max($values),
                ];
            }
        }
    }
    unset($result);
    // JSON is the default too, so results can always be archived without parsing prose.
    echo \json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $error) {
    \fwrite(STDERR, 'Pipeline benchmark failed: ' . $error->getMessage() . "\n");

    exit(1);
}
