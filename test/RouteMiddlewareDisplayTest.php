<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\RouteMiddlewareDisplay;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;

final class RouteMiddlewareDisplayTest extends TestCase
{
    public function testFormatsMiddlewareSpecificationsAndStringsInOrder(): void
    {
        self::assertSame(
            'middleware.first -> middleware.spec -> middleware.factory [factory: App\MiddlewareFactory] -> Handler::handle',
            RouteMiddlewareDisplay::format('Handler', 'handle', [
                'middleware.first',
                new MiddlewareSpecification('middleware.spec'),
                new MiddlewareSpecification('middleware.factory', 'App\MiddlewareFactory'),
            ])
        );
    }
}
