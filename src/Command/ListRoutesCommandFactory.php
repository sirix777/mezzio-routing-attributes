<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Command;

use Mezzio\Router\RouteCollector;
use Mezzio\Tooling\Routes\ConfigLoaderInterface;
use Psr\Container\ContainerInterface;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;

use function file_exists;
use function interface_exists;

final class ListRoutesCommandFactory
{
    /** @noRector StringClassNameToClassConstantRector */
    private const APPLICATION_SERVICE = 'Mezzio\Application';

    /** @noRector StringClassNameToClassConstantRector */
    private const MIDDLEWARE_FACTORY_SERVICE = 'Mezzio\MiddlewareFactory';
    private const DEFAULT_ROUTES_FILE = 'config/routes.php';

    public function __invoke(ContainerInterface $container): ListRoutesCommand
    {
        $rootConfig = $container->has('config') ? $container->get('config') : [];
        $config = RoutingAttributesConfig::fromRootConfig($rootConfig);

        /** @var RouteCollector $routeCollector */
        $routeCollector = $container->get(RouteCollector::class);
        $middlewareDisplayResolver = new RouteMiddlewareDisplayResolver($config->classicRoutesMiddlewareDisplay);

        $loadConfig = $this->createConfigLoader($container);

        return new ListRoutesCommand(
            new RouteTableProvider($routeCollector, $loadConfig),
            new RouteListFilter($middlewareDisplayResolver),
            new RouteListSorter(),
            new RouteListFormatter($middlewareDisplayResolver)
        );
    }

    /** @return null|callable():void */
    private function createConfigLoader(ContainerInterface $container): ?callable
    {
        if (
            interface_exists(ConfigLoaderInterface::class)
            && $container->has(ConfigLoaderInterface::class)
        ) {
            $configLoader = $container->get(ConfigLoaderInterface::class);

            return function() use ($configLoader): void {
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

        return function() use ($container): void {
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
