<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\TestAsset;

use Mezzio\Router\Route;
use Mezzio\Router\RouteCollectorInterface;
use Psr\Http\Server\MiddlewareInterface;

use function spl_object_id;

final class RecordingRouteCollector implements RouteCollectorInterface
{
    public int $routeCalls = 0;

    public ?Route $lastRoute = null;

    public mixed $lastName = 'not-called';

    /** @var list<Route> */
    public array $routes = [];

    /** @var list<int> */
    public array $middlewareIds = [];

    /**
     * @param null|callable(Route): void $configureRoute
     */
    public function __construct(private readonly mixed $configureRoute = null) {}

    public function route(string $path, MiddlewareInterface $middleware, ?array $methods = null, ?string $name = null): Route
    {
        ++$this->routeCalls;
        $this->middlewareIds[] = spl_object_id($middleware);
        $this->lastName = $name;

        $route = new Route($path, $middleware, $methods, $name);
        if (null !== $this->configureRoute) {
            ($this->configureRoute)($route);
        }

        $this->lastRoute = $route;
        $this->routes[] = $route;

        return $route;
    }

    public function get(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
    {
        return $this->route($path, $middleware, ['GET'], $name);
    }

    public function post(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
    {
        return $this->route($path, $middleware, ['POST'], $name);
    }

    public function put(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
    {
        return $this->route($path, $middleware, ['PUT'], $name);
    }

    public function patch(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
    {
        return $this->route($path, $middleware, ['PATCH'], $name);
    }

    public function delete(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
    {
        return $this->route($path, $middleware, ['DELETE'], $name);
    }

    public function any(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
    {
        return $this->route($path, $middleware, null, $name);
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }
}
