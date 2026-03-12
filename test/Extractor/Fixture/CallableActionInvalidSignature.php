<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

final class CallableActionInvalidSignature
{
    #[Get('/invalid-signature', name: 'callable.invalid.signature')]
    public function index(string $required): ResponseInterface
    {
        throw new RuntimeException($required);
    }
}
