<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Attribute\Route;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidRouteDefinitionException;
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

    public function testTrimsRouteDataAndKeepsNullOptionalValues(): void
    {
        $normalizer = new RouteDataNormalizer();

        self::assertSame('/orders', $normalizer->normalizePath('App\Handler\ExampleHandler', ' /orders '));
        self::assertSame(['GET', 'POST'], $normalizer->normalizeMethods('App\Handler\ExampleHandler', [' GET ', ' ', 'POST']));
        self::assertNull($normalizer->normalizeMethods('App\Handler\ExampleHandler', null));
        self::assertSame('orders.list', $normalizer->normalizeName('App\Handler\ExampleHandler', ' orders.list '));
        self::assertNull($normalizer->normalizeName('App\Handler\ExampleHandler', null));
        self::assertSame([], $normalizer->normalizeMiddlewareServices('App\Handler\ExampleHandler', null));
        self::assertSame([], $normalizer->normalizeMiddlewareServices('App\Handler\ExampleHandler', []));
    }

    /** @param callable(RouteDataNormalizer): mixed $normalize */
    #[DataProvider('invalidRouteData')]
    public function testRejectsBlankRouteData(callable $normalize, string $message): void
    {
        $this->expectException(InvalidRouteDefinitionException::class);
        $this->expectExceptionMessage($message);

        $normalize(new RouteDataNormalizer());
    }

    /** @return iterable<string, array{callable(RouteDataNormalizer): mixed, string}> */
    public static function invalidRouteData(): iterable
    {
        $class = 'App\Handler\ExampleHandler';

        yield 'empty path' => [
            static fn (RouteDataNormalizer $normalizer): string => $normalizer->normalizePath($class, ''),
            'Route path for class "App\Handler\ExampleHandler" must be a non-empty string.',
        ];

        yield 'blank path' => [
            static fn (RouteDataNormalizer $normalizer): string => $normalizer->normalizePath($class, "  \t"),
            'Route path for class',
        ];

        yield 'blank class prefix' => [
            static fn (RouteDataNormalizer $normalizer): string => $normalizer->prependClassPrefixes($class, '/orders', [new Route(' ')]),
            'Route path for class',
        ];

        yield 'empty methods list' => [
            static fn (RouteDataNormalizer $normalizer): ?array => $normalizer->normalizeMethods($class, []),
            'Route methods for class "App\Handler\ExampleHandler" must contain at least one non-empty HTTP method',
        ];

        yield 'blank methods only' => [
            static fn (RouteDataNormalizer $normalizer): ?array => $normalizer->normalizeMethods($class, [' ', '']),
            'Route methods for class',
        ];

        yield 'blank name' => [
            static fn (RouteDataNormalizer $normalizer): ?string => $normalizer->normalizeName($class, ' '),
            'Route name for class "App\Handler\ExampleHandler" must be a non-empty string when provided.',
        ];

        yield 'blank middleware only' => [
            static fn (RouteDataNormalizer $normalizer): array => $normalizer->normalizeMiddlewareServices($class, [' ', '']),
            'Route middleware list for class "App\Handler\ExampleHandler" must contain at least one non-empty service name',
        ];
    }
}
