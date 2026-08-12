<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes;

use Mezzio\Router\RouteCollectorInterface;
use Psr\Http\Server\MiddlewareInterface;
use Sirix\Mezzio\Routing\Attributes\Command\RouteMiddlewareDisplayResolver;

use function trim;

/** @internal */
final readonly class RouteRegistrar
{
    /**
     * @param array<RouteDefinition> $routes
     */
    public function register(RouteCollectorInterface $collector, array $routes, MiddlewarePipelineFactory $pipelineFactory): void
    {
        foreach ($routes as $route) {
            self::registerPreparedRoute(
                $collector,
                $pipelineFactory->createFromSignature(
                    $route->handlerService,
                    $route->handlerMethod,
                    $route->middlewareServices
                ),
                $route->path,
                $route->methods,
                self::normalizeRouteName($route->name),
                $route->defaults,
                RouteMiddlewareDisplay::format(
                    $route->handlerService,
                    $route->handlerMethod,
                    $route->middlewareServices
                )
            );
        }
    }

    /**
     * @param list<MiddlewareInterface>                                                                                         $compiledMiddlewares
     * @param list<array{0: non-empty-string, 1: null|list<string>, 2: int, 3: null|non-empty-string, 4: array<string, mixed>}> $routeRows
     * @param list<non-empty-string>                                                                                            $middlewareDisplays
     *
     * @internal used by cold and compiled route registration
     */
    public static function registerPreparedRows(
        RouteCollectorInterface $collector,
        array $compiledMiddlewares,
        array $routeRows,
        array $middlewareDisplays
    ): void {
        foreach ($routeRows as $row) {
            self::registerPreparedRoute(
                $collector,
                $compiledMiddlewares[$row[2]],
                $row[0],
                $row[1],
                $row[3],
                $row[4],
                $middlewareDisplays[$row[2]]
            );
        }
    }

    /**
     * @return null|non-empty-string
     *
     * @internal used by cache artifact generation
     */
    public static function normalizeRouteName(?string $name): ?string
    {
        if (null === $name) {
            return null;
        }

        $name = trim($name);

        return '' === $name ? null : $name;
    }

    /**
     * @param null|list<string>     $methods
     * @param null|non-empty-string $name
     * @param non-empty-string      $path
     * @param array<string, mixed>  $defaults
     * @param non-empty-string      $middlewareDisplay
     *
     * @internal shared by cold and compiled route registration
     */
    private static function registerPreparedRoute(
        RouteCollectorInterface $collector,
        MiddlewareInterface $middleware,
        string $path,
        ?array $methods,
        ?string $name,
        array $defaults,
        string $middlewareDisplay
    ): void {
        $registeredRoute                                                          = $collector->route($path, $middleware, $methods, $name);
        $options                                                                  = $registeredRoute->getOptions();
        $options[RouteMiddlewareDisplayResolver::ROUTE_OPTION_MIDDLEWARE_DISPLAY] = $middlewareDisplay;
        if ([] !== $defaults) {
            $options = [...$options, ...$defaults];
        }
        $registeredRoute->setOptions($options);
    }
}
