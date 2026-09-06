<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Discovery\Fixture\AbstractRoutes\Callable;

use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

abstract class AbstractHandler
{
    #[Get('/inherited')]
    public function run(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return (new Response())->withHeader('X-Handler', static::class);
    }
}
