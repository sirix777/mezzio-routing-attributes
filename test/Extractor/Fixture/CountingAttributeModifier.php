<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Attribute;
use Sirix\Mezzio\Routing\Contracts\RouteAttributeModifierInterface;

#[Attribute(Attribute::TARGET_CLASS)]
final class CountingAttributeModifier implements RouteAttributeModifierInterface
{
    public static int $instances = 0;

    public function __construct()
    {
        ++self::$instances;
    }

    public function getMiddleware(): array
    {
        return ['counting.modifier'];
    }

    public function getDefaults(): array
    {
        return [
            'counting' => true,
        ];
    }
}
