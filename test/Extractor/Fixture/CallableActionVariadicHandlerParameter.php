<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

final class CallableActionVariadicHandlerParameter
{
    #[Get('/variadic-handler-parameter', name: 'callable.variadic-handler-parameter')]
    public function index(RequestHandlerInterface|ServerRequestInterface ...$arguments): ResponseInterface
    {
        unset($arguments);

        throw new RuntimeException('Not implemented in test fixture.');
    }
}
