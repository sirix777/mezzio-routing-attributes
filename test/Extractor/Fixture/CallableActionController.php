<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

final class CallableActionController
{
    #[Get('/callable-action', name: 'callable.action')]
    public function index(mixed ...$args): ResponseInterface
    {
        // Variadic signature keeps fixture compatible with callable handler invocation.
        unset($args);

        throw new RuntimeException('Not implemented in test fixture.');
    }
}
