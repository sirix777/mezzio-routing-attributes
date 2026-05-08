<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture;

use Attribute;
use RuntimeException;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class ExplodingNonModifierAttribute
{
    public function __construct()
    {
        throw new RuntimeException('This attribute must not be instantiated during modifier collection.');
    }
}
