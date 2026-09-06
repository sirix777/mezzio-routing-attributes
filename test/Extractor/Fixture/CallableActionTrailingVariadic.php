<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Laminas\Diactoros\Response;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

use function assert;

final class CallableActionTrailingVariadic
{
    /** @var array<array-key, mixed> */
    public array $rest = [];

    #[Get('/trailing-variadic')]
    public function run(ServerRequestInterface $request, RequestHandlerInterface $handler, mixed ...$rest): ResponseInterface
    {
        $this->rest = $rest;

        return $handler->handle($request);
    }

    public function typedTrailingVariadic(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
        string ...$rest
    ): ResponseInterface {
        $this->rest = $rest;

        return $handler->handle($request);
    }

    public function handlerVariadic(ServerRequestInterface $request, RequestHandlerInterface ...$handlers): ResponseInterface
    {
        return $handlers[0]->handle($request);
    }

    public function allVariadic(RequestHandlerInterface|ServerRequestInterface ...$arguments): ResponseInterface
    {
        [$request, $handler] = $arguments;
        assert($request instanceof ServerRequestInterface);
        assert($handler instanceof RequestHandlerInterface);

        return $handler->handle($request);
    }

    public function optional(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
        string $value = 'default'
    ): ResponseInterface {
        $this->rest = [$value];

        return $handler->handle($request);
    }

    public function union(ServerRequestInterface|string $request, RequestHandlerInterface|string $handler): Response|ResponseInterface
    {
        assert($request instanceof ServerRequestInterface);
        assert($handler instanceof RequestHandlerInterface);

        return $handler->handle($request);
    }

    public function intersection(
        MessageInterface&ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): MessageInterface&ResponseInterface {
        return $handler->handle($request);
    }

    public function requiredThird(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
        string $required,
        mixed ...$rest
    ): ResponseInterface {
        unset($required, $rest);

        return $handler->handle($request);
    }

    public function requiredThirdWithoutVariadic(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
        string $required
    ): ResponseInterface {
        unset($required);

        return $handler->handle($request);
    }

    public function incompatibleHandlerVariadic(ServerRequestInterface $request, string ...$handlers): ResponseInterface
    {
        unset($request, $handlers);

        throw new RuntimeException('Invalid fixture must not execute.');
    }

    public function incompatibleAllVariadic(ServerRequestInterface ...$requests): ResponseInterface
    {
        unset($requests);

        throw new RuntimeException('Invalid fixture must not execute.');
    }
}
