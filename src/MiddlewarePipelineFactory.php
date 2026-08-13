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
    private array $middlewareBySignature = [];

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ServiceMiddlewareResolver $serviceMiddlewareResolver
    ) {}

    /**
     * @param list<non-empty-string> $middlewareServices
     */
    public function createFromSignature(string $handlerService, string $handlerMethod, array $middlewareServices): MiddlewareInterface
    {
        $signatureKey = $this->signatureKey($handlerService, $handlerMethod, $middlewareServices);
        if (isset($this->middlewareBySignature[$signatureKey])) {
            return $this->middlewareBySignature[$signatureKey];
        }

        return $this->middlewareBySignature[$signatureKey] = $this->createUncachedFromSignature(
            $handlerService,
            $handlerMethod,
            $middlewareServices
        );
    }

    /**
     * Constructs a pipeline without consulting or updating the compiled-signature cache.
     *
     * @param list<non-empty-string> $middlewareServices
     *
     * @internal used by generated route-cache artifacts, which already deduplicate signatures
     */
    public function createUncachedFromSignature(
        string $handlerService,
        string $handlerMethod,
        array $middlewareServices
    ): MiddlewareInterface {
        $middlewares = [];
        foreach ($middlewareServices as $serviceName) {
            $middlewares[] = $this->createServiceMiddleware($serviceName, 'process');
        }

        $middlewares[] = $this->createServiceMiddleware($handlerService, $handlerMethod);

        return 1 === count($middlewares)
            ? $middlewares[0]
            : $this->createPipeline($middlewares);
    }

    private function createServiceMiddleware(string $serviceName, string $methodName): MiddlewareInterface
    {
        return new LazyServiceMiddleware(
            $this->container,
            $this->serviceMiddlewareResolver,
            $serviceName,
            $methodName
        );
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
    private function signatureKey(string $handlerService, string $handlerMethod, array $middlewareServices): string
    {
        return $handlerService . "\0" . $handlerMethod . "\0" . implode("\0", $middlewareServices);
    }
}
