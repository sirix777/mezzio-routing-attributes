<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Attribute;
use Sirix\Mezzio\Routing\Contracts\AggregatingRouteAttributeModifierInterface;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class AggregatingTestAttributeModifier implements AggregatingRouteAttributeModifierInterface
{
    /**
     * @param non-empty-string       $mapping
     * @param non-empty-string       $uniqueMiddlewareKey
     * @param non-empty-string       $uniqueMiddleware
     * @param list<non-empty-string> $middleware
     * @param array<string, mixed>   $defaults
     */
    public function __construct(
        private string $mapping,
        private string $uniqueMiddlewareKey,
        private string $uniqueMiddleware,
        private array $middleware = [],
        private array $defaults = []
    ) {}

    /**
     * @return list<non-empty-string>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaults(): array
    {
        return $this->defaults;
    }

    /**
     * @param array<string, mixed> $defaults
     *
     * @return array<string, mixed>
     */
    public function mergeDefaults(array $defaults): array
    {
        $mappings   = $defaults['mappings'] ?? [];
        $mappings[] = $this->mapping;

        return [
            ...$defaults,
            'mappings' => $mappings,
        ];
    }

    /**
     * @return array<non-empty-string, non-empty-string>
     */
    public function getUniqueMiddleware(): array
    {
        return [
            $this->uniqueMiddlewareKey => $this->uniqueMiddleware,
        ];
    }
}
