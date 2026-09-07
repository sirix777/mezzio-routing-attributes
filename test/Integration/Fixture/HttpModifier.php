<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Integration\Fixture;

use Attribute;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;
use Sirix\Mezzio\Routing\Contracts\RouteAttributeModifierInterface;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class HttpModifier implements RouteAttributeModifierInterface
{
    public function getMiddleware(): array
    {
        return [
            new MiddlewareSpecification('http.specification', HttpSpecificationFactory::class, [
                'value' => 'configured',
            ])];
    }

    public function getDefaults(): array
    {
        return [
            'defaults' => [
                'id' => 'default-id',
            ],
            'plain'    => 'option-only',
        ];
    }
}
