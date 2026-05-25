<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[CountingAttributeModifier]
final class MultiMethodRouteWithClassModifierHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Not implemented in test fixture.');
    }

    #[Get('/counting/first', name: 'counting.first')]
    public function first(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Not implemented in test fixture.');
    }

    #[Get('/counting/second', name: 'counting.second')]
    public function second(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Not implemented in test fixture.');
    }
}
