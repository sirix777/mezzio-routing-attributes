<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[AggregatingTestAttributeModifier('query', 'request.mapper', 'mapper.middleware')]
final class MultipleAggregatingModifierHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Not implemented in test fixture.');
    }

    #[Get('/multiple-aggregating-modifiers', name: 'multiple.aggregating.modifiers')]
    #[AggregatingTestAttributeModifier('body', 'request.mapper', 'mapper.middleware')]
    #[AggregatingTestAttributeModifier('headers', 'request.validator', 'validator.middleware')]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Not implemented in test fixture.');
    }
}
