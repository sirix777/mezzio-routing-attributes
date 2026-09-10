<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[AggregatingTestAttributeModifier('query', 'request.mapper', 'mapper.middleware', defaults: [
    'mappings' => ['query'],
])]
final class LegacyDefaultsAggregatingModifierHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Not implemented in test fixture.');
    }

    #[Get('/legacy-aggregating-defaults', name: 'legacy.aggregating.defaults')]
    #[AggregatingTestAttributeModifier('body', 'request.mapper', 'mapper.middleware', defaults: [
        'mappings' => ['body'],
    ])]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Not implemented in test fixture.');
    }
}
