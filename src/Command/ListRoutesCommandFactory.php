<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Command;

use Closure;
use Mezzio\Router\RouteCollector;
use Mezzio\Tooling\Routes\ConfigLoaderInterface;
use Psr\Container\ContainerInterface;

use function file_exists;
use function interface_exists;

final class ListRoutesCommandFactory
{
    /** @noRector StringClassNameToClassConstantRector */
    private const APPLICATION_SERVICE = 'Mezzio\Application';

    /** @noRector StringClassNameToClassConstantRector */
    private const MIDDLEWARE_FACTORY_SERVICE = 'Mezzio\MiddlewareFactory';
    private const DEFAULT_ROUTES_FILE        = 'config/routes.php';

    public function __invoke(ContainerInterface $container): ListRoutesCommand
    {
        /** @var RouteCollector $routeCollector */
        $routeCollector = $container->get(RouteCollector::class);

        /** @var RouteMiddlewareDisplayResolver $middlewareDisplayResolver */
        $middlewareDisplayResolver = $container->get(RouteMiddlewareDisplayResolver::class);

        return new ListRoutesCommand(
            new RouteTableProvider($routeCollector, $this->createConfigLoader($container)),
            new RouteListFilter($middlewareDisplayResolver),
            new RouteListSorter(),
            new RouteListFormatter($middlewareDisplayResolver)
        );
    }

    /** @return null|Closure(): void */
    private function createConfigLoader(ContainerInterface $container): ?Closure
    {
        if (
            interface_exists(ConfigLoaderInterface::class)
            && $container->has(ConfigLoaderInterface::class)
        ) {
            $configLoader = $container->get(ConfigLoaderInterface::class);

            return static function() use ($configLoader): void {
                $configLoader->load();
            };
        }

        if (
            ! $container->has(self::APPLICATION_SERVICE)
            || ! $container->has(self::MIDDLEWARE_FACTORY_SERVICE)
            || ! file_exists(self::DEFAULT_ROUTES_FILE)
        ) {
            return null;
        }

        return static function() use ($container): void {
            /** @phpstan-ignore require.fileNotFound */
            $routes = require self::DEFAULT_ROUTES_FILE;
            $routes(
                $container->get(self::APPLICATION_SERVICE),
                $container->get(self::MIDDLEWARE_FACTORY_SERVICE),
                $container
            );
        };
    }
}
