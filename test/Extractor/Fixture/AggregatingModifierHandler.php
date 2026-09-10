<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[AggregatingTestAttributeModifier('query', 'request.mapper', 'mapper.middleware', ['class.middleware'], [
    'class' => true,
])]
final class AggregatingModifierHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Not implemented in test fixture.');
    }

    #[Get('/aggregating-modifier', name: 'aggregating.modifier')]
    #[AggregatingTestAttributeModifier('body', 'request.mapper', 'mapper.middleware', ['method.middleware'], [
        'method' => true,
    ])]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Not implemented in test fixture.');
    }
}
