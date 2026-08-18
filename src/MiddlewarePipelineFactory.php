<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Contracts\Exception\InvalidMiddlewareSpecificationException;
use Sirix\Mezzio\Routing\Contracts\MiddlewareFactoryInterface;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;

use function count;

final class MiddlewarePipelineFactory
{
    /** @var array<string, MiddlewareInterface> */
    private array $middlewareBySignature = [];

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ServiceMiddlewareResolver $serviceMiddlewareResolver
    ) {}

    /**
     * @param list<MiddlewareSpecification|non-empty-string> $middlewareServices
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
     * A specification entry must define a factory; factory-less entries must use the string
     * service-id path instead.
     *
     * @param list<MiddlewareSpecification|non-empty-string> $middlewareServices
     *
     * @internal used by generated route-cache artifacts, which already deduplicate signatures
     */
    public function createUncachedFromSignature(
        string $handlerService,
        string $handlerMethod,
        array $middlewareServices
    ): MiddlewareInterface {
        $middlewares = [];
        foreach ($middlewareServices as $middleware) {
            $middlewares[] = $middleware instanceof MiddlewareSpecification
                ? $this->createSpecMiddleware($middleware)
                : $this->createServiceMiddleware($middleware, 'process');
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

    private function createSpecMiddleware(MiddlewareSpecification $specification): MiddlewareInterface
    {
        if (null === $specification->factory) {
            throw new InvalidMiddlewareSpecificationException(
                'Middleware specification factory must be set when resolving a pipeline entry.'
            );
        }

        /** @var class-string<MiddlewareFactoryInterface> $factoryClass */
        $factoryClass = $specification->factory;

        return new LazySpecMiddleware($this->container, $factoryClass, $specification);
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
     * @param list<MiddlewareSpecification|non-empty-string> $middlewareServices
     */
    private function signatureKey(string $handlerService, string $handlerMethod, array $middlewareServices): string
    {
        return MiddlewareSignatureKey::for($handlerService, $handlerMethod, $middlewareServices);
    }
}
