<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionMethod;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidServiceDefinitionException;

use function count;
use function get_debug_type;
use function is_object;
use function method_exists;

final readonly class MiddlewarePipelineFactory
{
    public function __construct(private ContainerInterface $container) {}

    /**
     * @return array{middleware: MiddlewareInterface, middlewareDisplay: non-empty-string}
     */
    public function create(RouteDefinition $route): array
    {
        $middlewares = [];
        $middlewareDisplay = '';
        foreach ($route->middlewareServices as $serviceName) {
            $service = $this->container->get($serviceName);
            $middlewares[] = $this->prepareMiddleware($serviceName, $service, 'process');
            $middlewareDisplay = '' === $middlewareDisplay
                ? $serviceName
                : $middlewareDisplay . ' -> ' . $serviceName;
        }

        $handlerService = $this->container->get($route->handlerService);
        $middlewares[] = $this->prepareMiddleware($route->handlerService, $handlerService, $route->handlerMethod);
        $handlerDisplay = $route->handlerService . '::' . $route->handlerMethod;
        $middlewareDisplay = '' === $middlewareDisplay
            ? $handlerDisplay
            : $middlewareDisplay . ' -> ' . $handlerDisplay;

        $middleware = 1 === count($middlewares)
            ? $middlewares[0]
            : $this->createPipeline($middlewares);

        return [
            'middleware' => $middleware,
            'middlewareDisplay' => $middlewareDisplay,
        ];
    }

    private function prepareMiddleware(string $serviceName, mixed $service, string $methodName): MiddlewareInterface
    {
        if ($service instanceof MiddlewareInterface) {
            if ('process' !== $methodName) {
                return $this->createMethodMiddleware($serviceName, $service, $methodName);
            }

            return $service;
        }

        if ($service instanceof RequestHandlerInterface) {
            if ('handle' === $methodName) {
                return new class($service) implements MiddlewareInterface {
                    public function __construct(private readonly RequestHandlerInterface $handler) {}

                    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                    {
                        return $this->handler->handle($request);
                    }
                };
            }

            return $this->createMethodMiddleware($serviceName, $service, $methodName);
        }

        if ('process' !== $methodName) {
            return $this->createMethodMiddleware($serviceName, $service, $methodName);
        }

        throw InvalidServiceDefinitionException::invalidMiddlewareServiceType($serviceName, get_debug_type($service));
    }

    private function createMethodMiddleware(string $serviceName, mixed $service, string $methodName): MiddlewareInterface
    {
        if (! is_object($service)) {
            throw InvalidServiceDefinitionException::invalidMiddlewareServiceType($serviceName, get_debug_type($service));
        }

        if (! method_exists($service, $methodName)) {
            throw InvalidServiceDefinitionException::missingMethod($serviceName, $methodName);
        }

        $reflection = new ReflectionMethod($service, $methodName);
        if (! $reflection->isPublic()) {
            throw InvalidServiceDefinitionException::nonPublicMethod($service::class, $methodName);
        }

        return new MethodInvokerMiddleware($service, $methodName);
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
}
