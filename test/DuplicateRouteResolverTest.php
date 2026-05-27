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

    public function testThrowsOnDuplicatePathAndMethodsWithThrowStrategy(): void
    {
        $resolver = new DuplicateRouteResolver(DuplicateRouteResolver::STRATEGY_THROW);

        $this->expectException(DuplicateRouteDefinitionException::class);

        $resolver->resolve([
            new RouteDefinition('/same', ['GET'], 'service.a', 'handle', [], 'route.a'),
            new RouteDefinition('/same', ['GET'], 'service.b', 'handle', [], 'route.b'),
        ]);
    }

    public function testSkipsDuplicatePathAndMethodsWithIgnoreStrategy(): void
    {
        $resolver = new DuplicateRouteResolver(DuplicateRouteResolver::STRATEGY_IGNORE);

        $resolved = $resolver->resolve([
            new RouteDefinition('/same', ['GET'], 'service.a', 'handle', [], 'route.a'),
            new RouteDefinition('/same', ['GET'], 'service.b', 'handle', [], 'route.b'),
            new RouteDefinition('/same', ['POST'], 'service.c', 'handle', [], 'route.c'),
        ]);

        self::assertCount(2, $resolved);
        self::assertSame('route.a', $resolved[0]->name);
        self::assertSame('route.c', $resolved[1]->name);
    }

    public function testTreatsMethodOrderAndCaseAsSameSignature(): void
    {
        $resolver = new DuplicateRouteResolver(DuplicateRouteResolver::STRATEGY_THROW);

        $this->expectException(DuplicateRouteDefinitionException::class);

        $resolver->resolve([
            new RouteDefinition('/same', ['post', 'GET'], 'service.a', 'handle', [], 'route.a'),
            new RouteDefinition('/same', ['GET', 'POST'], 'service.b', 'handle', [], 'route.b'),
        ]);
    }

    public function testTreatsAnyMethodAsItsOwnSignature(): void
    {
        $resolver = new DuplicateRouteResolver(DuplicateRouteResolver::STRATEGY_IGNORE);

        $resolved = $resolver->resolve([
            new RouteDefinition('/same', null, 'service.a', 'handle', [], 'route.a'),
            new RouteDefinition('/same', ['GET'], 'service.b', 'handle', [], 'route.b'),
            new RouteDefinition('/same', null, 'service.c', 'handle', [], 'route.c'),
        ]);

        self::assertCount(2, $resolved);
        self::assertSame('route.a', $resolved[0]->name);
        self::assertSame('route.b', $resolved[1]->name);
    }
}
