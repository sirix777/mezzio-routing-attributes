<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor;

use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractor;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorFactory;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\CallableActionController;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\InMemoryContainer;

final class AttributeRouteExtractorFactoryTest extends TestCase
{
    public function testCreatesExtractorWithDefaultMode(): void
    {
        $container = new InMemoryContainer([
            'config' => [],
        ]);

        $extractor = (new AttributeRouteExtractorFactory())($container);

        self::assertInstanceOf(AttributeRouteExtractor::class, $extractor);
    }

    public function testCreatesExtractorWithCallableMode(): void
    {
        $container = new InMemoryContainer([
            'config' => [
                'routing_attributes' => [
                    'handlers' => [
                        'mode' => 'callable',
                    ],
                ],
            ],
        ]);

        $extractor = (new AttributeRouteExtractorFactory())($container);
        $routes = $extractor->extract([CallableActionController::class]);

        self::assertCount(1, $routes);
        self::assertSame('index', $routes[0]->handlerMethod);
    }

    public function testThrowsForInvalidHandlersMode(): void
    {
        $container = new InMemoryContainer([
            'config' => [
                'routing_attributes' => [
                    'handlers' => [
                        'mode' => 'invalid',
                    ],
                ],
            ],
        ]);

        $this->expectException(InvalidConfigurationException::class);

        (new AttributeRouteExtractorFactory())($container);
    }
}
