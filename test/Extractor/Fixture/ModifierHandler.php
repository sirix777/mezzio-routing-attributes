<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Attribute\Route;

#[Route(path: '/modifier', name: 'modifier.route')]
#[TestAttributeModifier(
    middleware: ['modifier.middleware'],
    defaults: ['modifier_key' => 'modifier_value']
)]
final class ModifierHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Not implemented');
    }
}
