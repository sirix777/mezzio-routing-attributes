<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

final class CallableActionWithTrailingOptionalParameter
{
    #[Get('/trailing-optional-parameter', name: 'callable.trailing-optional-parameter')]
    public function index(
        ServerRequestInterface $request,
        ?RequestHandlerInterface $handler = null,
        string $mode = 'default'
    ): ResponseInterface {
        unset($request, $handler, $mode);

        throw new RuntimeException('Not implemented in test fixture.');
    }
}
