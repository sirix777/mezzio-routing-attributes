<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Attribute;
use Sirix\Mezzio\Routing\Contracts\AggregatingRouteAttributeModifierInterface;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class ConflictingUniqueMiddlewareModifier implements AggregatingRouteAttributeModifierInterface
{
    /**
     * @param non-empty-string                   $key
     * @param non-empty-string                   $service
     * @param null|non-empty-string              $factory
     * @param array<string, list<scalar>|scalar> $arguments
     */
    public function __construct(
        private string $key,
        private string $service,
        private ?string $factory = null,
        private array $arguments = []
    ) {}

    public function getMiddleware(): array
    {
        return [];
    }

    public function getDefaults(): array
    {
        return [];
    }

    public function mergeDefaults(array $defaults): array
    {
        return $defaults;
    }

    public function getUniqueMiddleware(): array
    {
        return [
            $this->key => null === $this->factory
                ? $this->service
                : new MiddlewareSpecification($this->service, $this->factory, $this->arguments),
        ];
    }
}
