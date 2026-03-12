<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Psr\Http\Message\ServerRequestInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

final class CallableActionInvalidReturnType
{
    #[Get('/invalid-return', name: 'callable.invalid.return')]
    public function index(ServerRequestInterface $request): string
    {
        return $request::class;
    }
}
