<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Attribute;
use Sirix\Mezzio\Routing\Attributes\Contract\RouteAttributeModifierInterface;

use function array_filter;
use function array_map;
use function array_values;
use function trim;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class TestAttributeModifier implements RouteAttributeModifierInterface
{
    /**
     * @param list<string>         $middleware
     * @param array<string, mixed> $defaults
     */
    public function __construct(private array $middleware = [], private array $defaults = []) {}

    /**
     * @return list<non-empty-string>
     */
    public function getMiddleware(): array
    {
        $normalized = array_filter(
            array_map(trim(...), $this->middleware),
            static fn (string $s): bool => '' !== $s
        );

        return array_values($normalized);
    }

    public function getDefaults(): array
    {
        return $this->defaults;
    }
}
