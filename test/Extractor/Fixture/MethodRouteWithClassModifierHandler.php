<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[TestAttributeModifier(middleware: ['class.modifier'], defaults: ['scope' => 'class', 'classOnly' => true])]
final class MethodRouteWithClassModifierHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Not implemented in test fixture.');
    }

    #[Get('/method-modifier', name: 'method.modifier', middleware: ['route.middleware'])]
    #[TestAttributeModifier(middleware: ['method.modifier'], defaults: ['scope' => 'method', 'methodOnly' => 1])]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Not implemented in test fixture.');
    }
}
