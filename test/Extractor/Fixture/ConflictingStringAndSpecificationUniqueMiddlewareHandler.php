<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[ConflictingUniqueMiddlewareModifier('request.mapper', 'mapper')]
final class ConflictingStringAndSpecificationUniqueMiddlewareHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Not implemented in test fixture.');
    }

    #[Get('/conflicting-string-and-specification', name: 'conflicting.string.and.specification')]
    #[ConflictingUniqueMiddlewareModifier('request.mapper', 'mapper', 'mapper.factory')]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Not implemented in test fixture.');
    }
}
