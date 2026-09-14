<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Attribute\Route;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteDataNormalizer;
use Sirix\Mezzio\Routing\Contracts\Exception\InvalidMiddlewareSpecificationException;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;

final class RouteDataNormalizerTest extends TestCase
{
    public function testNormalizesStringsAndPreservesMiddlewareSpecifications(): void
    {
        $specification = new MiddlewareSpecification('middleware.service', 'Factory');

        self::assertSame(
            ['middleware.first', $specification, 'middleware.second'],
            (new RouteDataNormalizer())->normalizeMiddlewareServices(
                'App\Handler\ExampleHandler',
                [' middleware.first ', $specification, 'middleware.second']
            )
        );
    }

    public function testRejectsNonStringNonSpecificationMiddlewareEntry(): void
    {
        $this->expectException(InvalidMiddlewareSpecificationException::class);

        (new RouteDataNormalizer())->normalizeMiddlewareServices('App\Handler\ExampleHandler', [123]);
    }

    public function testCollectClassMiddlewareConcatenatesAllClassRoutes(): void
    {
        $classRoutes = [
            new Route('/one', null, null, [' middleware.first ', ' middleware.class ']),
            new Route('/two', null, null, ['middleware.second']),
        ];

        self::assertSame(
            [' middleware.first ', ' middleware.class ', 'middleware.second'],
            (new RouteDataNormalizer())->collectClassMiddleware($classRoutes)
        );
    }

    public function testCollectClassMiddlewareReturnsEmptyListWithoutClassRoutes(): void
    {
        self::assertSame([], (new RouteDataNormalizer())->collectClassMiddleware([]));
    }

    public function testMergeMiddlewareServicesNormalizesClassMiddlewareAndAppendsRouteMiddleware(): void
    {
        $normalizer = new RouteDataNormalizer();

        self::assertSame(
            ['middleware.first', 'middleware.second'],
            $normalizer->mergeMiddlewareServices(
                'App\Handler\ExampleHandler',
                $normalizer->collectClassMiddleware([
                    new Route('/one', null, null, [' middleware.first ']),
                ]),
                ['middleware.second']
            )
        );
    }
}
