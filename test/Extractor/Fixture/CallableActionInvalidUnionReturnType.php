<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

use function is_string;

final class CallableActionInvalidUnionReturnType
{
    #[Get('/invalid-union-return', name: 'callable.invalid.union.return')]
    public function index(ServerRequestInterface $request): ResponseInterface|string
    {
        $result = $request->getAttribute('result');
        if ($result instanceof ResponseInterface || is_string($result)) {
            return $result;
        }

        return $request::class;
    }
}
