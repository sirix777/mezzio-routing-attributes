<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Integration\Fixture;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

final readonly class HttpActions
{
    #[Get('/action', 'http.action', [HttpMiddleware::class])]
    public function action(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request->withAttribute('action', true))->withHeader('X-Action', 'executed');
    }

    #[Get('/optional/:id?', 'http.optional')]
    #[HttpModifier]
    public function optional(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse([
            'id'    => $request->getAttribute('id'),
            'plain' => $request->getAttribute('plain'),
        ]);
    }
}
