<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Integration\Fixture;

use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/entrypoint')]
final class PublicHelperMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return new Response(status: 201);
    }

    public function handle(string $value): string
    {
        return $value;
    }
}
