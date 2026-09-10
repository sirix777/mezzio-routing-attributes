<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[ConflictingUniqueMiddlewareModifier('request.mapper', 'mapper', 'first.factory', [
    'mode' => 'first',
])]
final class ConflictingSpecificationUniqueMiddlewareHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Not implemented in test fixture.');
    }

    #[Get('/conflicting-specification', name: 'conflicting.specification')]
    #[ConflictingUniqueMiddlewareModifier('request.mapper', 'mapper', 'second.factory', [
        'mode' => 'second',
    ])]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Not implemented in test fixture.');
    }
}
