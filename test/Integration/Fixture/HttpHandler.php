<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Integration\Fixture;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/handler/:id', 'http.handler', [HttpMiddleware::class])]
#[HttpModifier]
final readonly class HttpHandler implements RequestHandlerInterface
{
    public function __construct(private HttpTrace $trace) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->trace->events[] = 'handler';

        return new JsonResponse([
            'id'            => $request->getAttribute('id'),
            'input'         => $request->getAttribute('input'),
            'service'       => $request->getAttribute('service'),
            'specification' => $request->getAttribute('specification'),
        ], 201);
    }
}
