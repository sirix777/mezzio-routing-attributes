<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveredClassesResolverInterface;
use Sirix\Mezzio\Routing\Attributes\DuplicateRouteResolver;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;
use Sirix\Mezzio\Routing\Attributes\RouteDefinition;
use Sirix\Mezzio\Routing\Attributes\RouteDefinitionResolver;

final class RouteDefinitionResolverTest extends TestCase
{
    public function testMergesUniqueConfiguredAndDiscoveredClassesBeforeExtraction(): void
    {
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $extractor
            ->expects(self::once())
            ->method('extract')
            ->with(['ConfiguredRoute', 'SharedRoute', 'DiscoveredRoute'])
            ->willReturn([])
        ;
        $discoveredClassesResolver = $this->createMock(DiscoveredClassesResolverInterface::class);
        $discoveredClassesResolver
            ->expects(self::once())
            ->method('resolve')
            ->willReturn(['SharedRoute', 'DiscoveredRoute'])
        ;

        $routes = (new RouteDefinitionResolver(
            $extractor,
            new DuplicateRouteResolver(),
            $discoveredClassesResolver
        ))->resolve(['ConfiguredRoute', 'SharedRoute']);

        self::assertSame([], $routes);
    }

    public function testSkipsDiscoveryWhenItIsDisabled(): void
    {
        $extractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $route     = new RouteDefinition('/route', ['GET'], 'handler', 'process', []);
        $extractor
            ->expects(self::once())
            ->method('extract')
            ->with(['ConfiguredRoute'])
            ->willReturn([$route])
        ;
        $discoveredClassesResolver = $this->createMock(DiscoveredClassesResolverInterface::class);
        $discoveredClassesResolver->expects(self::never())->method('resolve');

        $routes = (new RouteDefinitionResolver(
            $extractor,
            new DuplicateRouteResolver(),
            $discoveredClassesResolver
        ))->resolve(['ConfiguredRoute'], false);

        self::assertSame([$route], $routes);
    }
}
