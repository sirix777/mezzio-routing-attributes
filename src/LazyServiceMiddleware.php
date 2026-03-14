<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class LazyServiceMiddleware implements MiddlewareInterface
{
    private ?MiddlewareInterface $resolved = null;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ServiceMiddlewareResolver $resolver,
        private readonly string $serviceName,
        private readonly string $methodName = 'process'
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (! $this->resolved instanceof MiddlewareInterface) {
            $service = $this->container->get($this->serviceName);
            $this->resolved = $this->resolver->resolve($this->serviceName, $service, $this->methodName);
        }

        return $this->resolved->process($request, $handler);
    }
}
