<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Attribute\Route;

#[Route(path: '/modifier-safe', name: 'modifier.safe')]
#[TestAttributeModifier(middleware: ['safe.middleware'])]
#[ExplodingNonModifierAttribute]
final class ModifierWithIrrelevantAttributeHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Not implemented');
    }
}
