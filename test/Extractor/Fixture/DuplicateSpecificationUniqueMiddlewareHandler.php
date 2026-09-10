<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[ConflictingUniqueMiddlewareModifier('request.mapper', 'mapper', 'mapper.factory', [
    'mode' => 'strict',
])]
final class DuplicateSpecificationUniqueMiddlewareHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Not implemented in test fixture.');
    }

    #[Get('/duplicate-specification', name: 'duplicate.specification')]
    #[ConflictingUniqueMiddlewareModifier('request.mapper', 'mapper', 'mapper.factory', [
        'mode' => 'strict',
    ])]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Not implemented in test fixture.');
    }
}
