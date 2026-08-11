<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Discovery;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Discovery\RoutableClassFilter;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\CallableActionController;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\NotMiddleware;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\PingHandler;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\PingRequestHandler;

final class RoutableClassFilterTest extends TestCase
{
    public function testFiltersOnlyRoutableClasses(): void
    {
        $result = (new RoutableClassFilter())->filter([
            PingHandler::class,
            PingRequestHandler::class,
            NotMiddleware::class,
            CallableActionController::class,
        ]);

        self::assertSame([
            PingHandler::class,
            PingRequestHandler::class,
        ], $result);
    }

    public function testAllowsOnlyPlainClassesWithMethodRouteAttributesWhenEnabled(): void
    {
        $result = (new RoutableClassFilter(true))->filter([
            PingHandler::class,
            PingRequestHandler::class,
            NotMiddleware::class,
            CallableActionController::class,
        ]);

        self::assertSame([
            PingHandler::class,
            PingRequestHandler::class,
            CallableActionController::class,
        ], $result);
    }
}
