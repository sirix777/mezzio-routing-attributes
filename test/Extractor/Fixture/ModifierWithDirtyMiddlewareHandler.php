<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Attribute\Route;

#[Route(path: '/modifier-dirty', name: 'modifier.dirty')]
#[TestAttributeModifier(middleware: ['  normalized.middleware  ', ''])]
final class ModifierWithDirtyMiddlewareHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Not implemented');
    }
}
