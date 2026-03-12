<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidServiceDefinitionException;

final readonly class MethodInvokerMiddleware implements MiddlewareInterface
{
    public function __construct(private object $service, private string $methodName) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        unset($handler);

        $response = $this->service->{$this->methodName}($request);

        if (! $response instanceof ResponseInterface) {
            throw InvalidServiceDefinitionException::invalidMethodReturnType(
                $this->service::class,
                $this->methodName
            );
        }

        return $response;
    }
}
