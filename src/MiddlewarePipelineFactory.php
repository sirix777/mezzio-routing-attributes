<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function count;
use function implode;

final class MiddlewarePipelineFactory
{
    /** @var array<string, MiddlewareInterface> */
    private array $compiledMiddlewareCache = [];

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ?ServiceMiddlewareResolver $serviceMiddlewareResolver = null
    ) {}

    /**
     * @return array{middleware: MiddlewareInterface, middlewareDisplay: non-empty-string}
     */
    public function create(RouteDefinition $route): array
    {
        $middleware = $this->createFromCompiled(
            $route->handlerService,
            $route->handlerMethod,
            $route->middlewareServices
        );

        return [
            'middleware' => $middleware,
            'middlewareDisplay' => $this->buildMiddlewareDisplay(
                $route->handlerService,
                $route->handlerMethod,
                $route->middlewareServices
            ),
        ];
    }

    /**
     * @param list<non-empty-string> $middlewareServices
     */
    public function createFromCompiled(string $handlerService, string $handlerMethod, array $middlewareServices): MiddlewareInterface
    {
        $cacheKey = $this->compiledRouteSignature($handlerService, $handlerMethod, $middlewareServices);
        if (isset($this->compiledMiddlewareCache[$cacheKey])) {
            return $this->compiledMiddlewareCache[$cacheKey];
        }

        $middlewares = [];
        foreach ($middlewareServices as $serviceName) {
            $middlewares[] = $this->createServiceMiddleware($serviceName, 'process');
        }

        $middlewares[] = $this->createServiceMiddleware($handlerService, $handlerMethod);

        $middleware = 1 === count($middlewares)
            ? $middlewares[0]
            : $this->createPipeline($middlewares);

        $this->compiledMiddlewareCache[$cacheKey] = $middleware;

        return $middleware;
    }

    private function createServiceMiddleware(string $serviceName, string $methodName): MiddlewareInterface
    {
        return new LazyServiceMiddleware(
            $this->container,
            $this->serviceMiddlewareResolver(),
            $serviceName,
            $methodName
        );
    }

    private function serviceMiddlewareResolver(): ServiceMiddlewareResolver
    {
        return $this->serviceMiddlewareResolver ?? new ServiceMiddlewareResolver();
    }

    /**
     * @param list<non-empty-string> $middlewareServices
     *
     * @return non-empty-string
     */
    private function buildMiddlewareDisplay(string $handlerService, string $handlerMethod, array $middlewareServices): string
    {
        $middlewareDisplay = '';
        foreach ($middlewareServices as $serviceName) {
            $middlewareDisplay = '' === $middlewareDisplay
                ? $serviceName
                : $middlewareDisplay . ' -> ' . $serviceName;
        }

        $handlerDisplay = $handlerService . '::' . $handlerMethod;

        return '' === $middlewareDisplay
            ? $handlerDisplay
            : $middlewareDisplay . ' -> ' . $handlerDisplay;
    }

    /**
     * @param non-empty-list<MiddlewareInterface> $middlewares
     */
    private function createPipeline(array $middlewares): MiddlewareInterface
    {
        return new class($middlewares) implements MiddlewareInterface {
            /**
             * @param non-empty-list<MiddlewareInterface> $middlewares
             */
            public function __construct(private readonly array $middlewares) {}

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $pipelineHandler = $handler;
                for ($i = count($this->middlewares) - 1; $i >= 0; --$i) {
                    $pipelineHandler = new MiddlewareHandler($this->middlewares[$i], $pipelineHandler);
                }

                return $pipelineHandler->handle($request);
            }
        };
    }

    /**
     * @param list<non-empty-string> $middlewareServices
     */
    private function compiledRouteSignature(string $handlerService, string $handlerMethod, array $middlewareServices): string
    {
        return $handlerService . "\0" . $handlerMethod . "\0" . implode("\0", $middlewareServices);
    }
}
