<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\DuplicateRouteResolver;
use Sirix\Mezzio\Routing\Attributes\Exception\DuplicateRouteDefinitionException;
use Sirix\Mezzio\Routing\Attributes\RouteDefinition;

final class DuplicateRouteResolverTest extends TestCase
{
    public function testThrowsOnDuplicateNameWithThrowStrategy(): void
    {
        $resolver = new DuplicateRouteResolver(DuplicateRouteResolver::STRATEGY_THROW);

        $this->expectException(DuplicateRouteDefinitionException::class);

        $resolver->resolve([
            new RouteDefinition('/one', ['GET'], 'service.a', 'handle', [], 'same'),
            new RouteDefinition('/two', ['GET'], 'service.b', 'handle', [], 'same'),
        ]);
    }

    public function testSkipsDuplicatesWithIgnoreStrategy(): void
    {
        $resolver = new DuplicateRouteResolver(DuplicateRouteResolver::STRATEGY_IGNORE);

        $resolved = $resolver->resolve([
            new RouteDefinition('/one', ['GET'], 'service.a', 'handle', [], 'same'),
            new RouteDefinition('/one', ['GET'], 'service.b', 'handle', [], 'same'),
            new RouteDefinition('/two', ['POST'], 'service.c', 'handle', [], 'other'),
        ]);

        self::assertCount(2, $resolved);
        self::assertSame('/one', $resolved[0]->path);
        self::assertSame('/two', $resolved[1]->path);
    }
}
