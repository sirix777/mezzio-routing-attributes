<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\MiddlewareSignatureKey;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;

final class MiddlewareSignatureKeyTest extends TestCase
{
    public function testUsesCanonicalSpecificationSignature(): void
    {
        $first = MiddlewareSignatureKey::for('handler', 'handle', [
            new MiddlewareSpecification("mapper\0factory", 'actual'),
        ]);
        $second = MiddlewareSignatureKey::for('handler', 'handle', [
            new MiddlewareSpecification('mapper', "factory\0actual"),
        ]);

        self::assertNotSame($first, $second);
    }

    public function testDoesNotEquateStringAndSpecificationMiddleware(): void
    {
        self::assertNotSame(
            MiddlewareSignatureKey::for('handler', 'handle', ['mapper']),
            MiddlewareSignatureKey::for('handler', 'handle', [new MiddlewareSpecification('mapper')])
        );
    }
}
