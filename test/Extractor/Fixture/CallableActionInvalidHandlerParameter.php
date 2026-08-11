<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

final class CallableActionInvalidHandlerParameter
{
    #[Get('/invalid-handler-parameter', name: 'callable.invalid.handler-parameter')]
    public function index(ServerRequestInterface $request, int $handler = 0): ResponseInterface
    {
        unset($request, $handler);

        throw new RuntimeException('Not implemented in test fixture.');
    }
}
