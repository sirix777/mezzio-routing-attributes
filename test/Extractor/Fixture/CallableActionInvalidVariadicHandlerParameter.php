<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

final class CallableActionInvalidVariadicHandlerParameter
{
    #[Get('/invalid-variadic-handler-parameter', name: 'callable.invalid-variadic-handler-parameter')]
    public function index(ServerRequestInterface ...$arguments): ResponseInterface
    {
        unset($arguments);

        throw new RuntimeException('Not implemented in test fixture.');
    }
}
