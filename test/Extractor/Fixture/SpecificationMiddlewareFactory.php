<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Sirix\Mezzio\Routing\Contracts\MiddlewareFactoryInterface;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;
use SirixTest\Mezzio\Routing\Attributes\TestMiddleware;

final class SpecificationMiddlewareFactory implements MiddlewareFactoryInterface
{
    public static int $createCalls = 0;

    public function create(ContainerInterface $container, MiddlewareSpecification $specification): MiddlewareInterface
    {
        ++self::$createCalls;

        return new TestMiddleware();
    }
}
