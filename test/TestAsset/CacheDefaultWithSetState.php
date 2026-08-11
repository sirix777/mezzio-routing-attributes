<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\TestAsset;

final readonly class CacheDefaultWithSetState
{
    public function __construct(public string $value) {}

    /**
     * @param array{value: string} $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['value']);
    }
}
