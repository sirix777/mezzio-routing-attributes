<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor;

use PHPUnit\Framework\TestCase;
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
}
