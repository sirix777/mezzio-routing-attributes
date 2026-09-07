<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\MiddlewarePipelineFactory;
use Sirix\Mezzio\Routing\Attributes\RouteDefinition;
use Sirix\Mezzio\Routing\Attributes\RouteMiddlewareDisplay;
use Sirix\Mezzio\Routing\Attributes\RouteRegistrar;
use Sirix\Mezzio\Routing\Attributes\ServiceMiddlewareResolver;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\InMemoryContainer;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\RecordingRouteCollector;

use function spl_autoload_register;
use function spl_autoload_unregister;
use function str_starts_with;

final class RouteMiddlewareDisplayTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRegistersMiddlewareDisplayWithoutLoadingCliClasses(): void
    {
        $rejectCliAutoload = static function(string $class): void {
            if (
                str_starts_with($class, 'Sirix\Mezzio\Routing\Attributes\Command\\')
                || str_starts_with($class, 'Symfony\Component\Console\\')
                || str_starts_with($class, 'Laminas\Cli\\')
                || str_starts_with($class, 'Mezzio\Tooling\\')
            ) {
                throw new RuntimeException('Core registration attempted to load CLI class: ' . $class);
            }
        };
        spl_autoload_register($rejectCliAutoload, true, true);

        try {
            $collector       = new RecordingRouteCollector();
            $pipelineFactory = new MiddlewarePipelineFactory(new InMemoryContainer([]), new ServiceMiddlewareResolver());

            (new RouteRegistrar())->register($collector, [
                new RouteDefinition('/example', ['GET'], 'handler', 'handle', ['middleware']),
            ], $pipelineFactory);

            self::assertNotNull($collector->lastRoute);
            self::assertSame(
                'middleware -> handler::handle',
                $collector->lastRoute->getOptions()['sirix_routing_attributes.middleware_display']
            );

            RouteRegistrar::registerPreparedRows(
                $collector,
                [$pipelineFactory->createUncachedFromSignature('handler', 'handle', ['middleware'])],
                [['/compiled', ['GET'], 0, null, []]],
                ['middleware -> handler::handle']
            );

            self::assertNotNull($collector->lastRoute);
            self::assertSame(
                'middleware -> handler::handle',
                $collector->lastRoute->getOptions()['sirix_routing_attributes.middleware_display']
            );
        } finally {
            spl_autoload_unregister($rejectCliAutoload);
        }
    }

    public function testFormatsMiddlewareSpecificationsAndStringsInOrder(): void
    {
        self::assertSame(
            'middleware.first -> middleware.spec -> middleware.factory [factory: App\MiddlewareFactory] -> Handler::handle',
            RouteMiddlewareDisplay::format('Handler', 'handle', [
                'middleware.first',
                new MiddlewareSpecification('middleware.spec'),
                new MiddlewareSpecification('middleware.factory', 'App\MiddlewareFactory'),
            ])
        );
    }
}
