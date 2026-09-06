<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Integration\Fixture;

use Attribute;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;
use Sirix\Mezzio\Routing\Contracts\RouteAttributeModifierInterface;

#[Attribute(Attribute::TARGET_CLASS)]
final class FactoryModifier implements RouteAttributeModifierInterface
{
    public static ?string $factory = null;

    public function getMiddleware(): array
    {
        return [new MiddlewareSpecification('middleware', self::$factory)];
    }

    public function getDefaults(): array
    {
        return [];
    }
}
