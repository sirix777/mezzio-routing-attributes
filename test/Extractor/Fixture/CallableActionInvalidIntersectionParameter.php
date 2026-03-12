<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;
use Stringable;

final class CallableActionInvalidIntersectionParameter
{
    #[Get('/invalid-intersection-parameter', name: 'callable.invalid.intersection.parameter')]
    public function index(ServerRequestInterface&Stringable $request): ResponseInterface
    {
        throw new RuntimeException($request->__toString());
    }
}
