<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;
use stdClass;

final class CallableActionUnionHandlerParameter
{
    #[Get('/union-handler-parameter', name: 'callable.union-handler-parameter')]
    public function index(ServerRequestInterface $request, RequestHandlerInterface|stdClass $handler): ResponseInterface
    {
        unset($request, $handler);

        throw new RuntimeException('Not implemented in test fixture.');
    }
}
