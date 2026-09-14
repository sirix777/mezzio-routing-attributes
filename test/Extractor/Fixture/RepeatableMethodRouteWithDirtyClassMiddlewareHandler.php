<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Route;

#[Route('/base', middleware: [' class.middleware '])]
#[Route('/extra', middleware: [' second.class.middleware '])]
final class RepeatableMethodRouteWithDirtyClassMiddlewareHandler implements MiddlewareInterface
{
    #[Route('/one', methods: ['GET'], middleware: ['method.one'])]
    #[Route('/two', methods: ['POST'], middleware: ['method.two'])]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        throw new LogicException('Not called.');
    }
}
