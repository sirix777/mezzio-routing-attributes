<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

final class CallableActionPrivateMethod
{
    public function exposeForFixtureUsage(): ResponseInterface
    {
        return $this->index();
    }

    #[Get('/private-action', name: 'callable.private')]
    private function index(): ResponseInterface
    {
        throw new RuntimeException('Not implemented in fixture.');
    }
}
