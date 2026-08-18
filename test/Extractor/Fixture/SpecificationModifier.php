<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Attribute;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;
use Sirix\Mezzio\Routing\Contracts\RouteAttributeModifierInterface;

#[Attribute(Attribute::TARGET_CLASS)]
final class SpecificationModifier implements RouteAttributeModifierInterface
{
    public function getMiddleware(): array
    {
        return [
            new MiddlewareSpecification('profile.middleware', SpecificationMiddlewareFactory::class, [
                'profile' => 'admin',
            ])];
    }

    public function getDefaults(): array
    {
        return [];
    }
}
