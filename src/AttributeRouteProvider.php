<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes;

use Mezzio\Router\RouteCollectorInterface;
use Psr\Container\ContainerInterface;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;

use function trim;

final readonly class AttributeRouteProvider
{
    public const DUPLICATE_STRATEGY_THROW = DuplicateRouteResolver::STRATEGY_THROW;
    public const DUPLICATE_STRATEGY_IGNORE = DuplicateRouteResolver::STRATEGY_IGNORE;
    public const ROUTE_OPTION_MIDDLEWARE_DISPLAY = 'sirix_routing_attributes.middleware_display';

    /**
     * @param list<string>                                                   $classes
     * @param self::DUPLICATE_STRATEGY_IGNORE|self::DUPLICATE_STRATEGY_THROW $duplicateStrategy
     */
    public function __construct(
        private ContainerInterface $container,
        private AttributeRouteExtractorInterface $extractor,
        private array $classes,
        private string $duplicateStrategy = self::DUPLICATE_STRATEGY_THROW,
        private ?string $cacheFile = null,
        private ?DuplicateRouteResolver $duplicateRouteResolver = null,
        private ?RouteDefinitionCache $routeDefinitionCache = null,
        private ?MiddlewarePipelineFactory $middlewarePipelineFactory = null
    ) {}

    public function registerRoutes(RouteCollectorInterface $collector): void
    {
        $pipelineFactory = $this->middlewarePipelineFactory();

        foreach ($this->resolveRoutes() as $route) {
            $pipeline = $pipelineFactory->create($route);
            $registeredRoute = $collector->route(
                $route->path,
                $pipeline['middleware'],
                $route->methods,
                $this->normalizeRouteName($route->name)
            );
            $routeOptions = $registeredRoute->getOptions();
            $routeOptions[self::ROUTE_OPTION_MIDDLEWARE_DISPLAY] = $pipeline['middlewareDisplay'];
            $registeredRoute->setOptions($routeOptions);
        }
    }

    /**
     * @return list<RouteDefinition>
     */
    private function resolveRoutes(): array
    {
        $cached = $this->routeDefinitionCache()->load();
        if (null !== $cached) {
            return $cached;
        }

        $routes = $this->duplicateRouteResolver()->resolve($this->extractor->extract($this->classes));
        $this->routeDefinitionCache()->save($routes);

        return $routes;
    }

    private function duplicateRouteResolver(): DuplicateRouteResolver
    {
        return $this->duplicateRouteResolver
            ?? new DuplicateRouteResolver($this->duplicateStrategy);
    }

    private function routeDefinitionCache(): RouteDefinitionCache
    {
        return $this->routeDefinitionCache
            ?? new RouteDefinitionCache($this->cacheFile);
    }

    private function middlewarePipelineFactory(): MiddlewarePipelineFactory
    {
        return $this->middlewarePipelineFactory
            ?? new MiddlewarePipelineFactory($this->container);
    }

    /**
     * @return null|non-empty-string
     */
    private function normalizeRouteName(?string $name): ?string
    {
        if (null === $name) {
            return null;
        }

        $name = trim($name);
        if ('' === $name) {
            return null;
        }

        return $name;
    }
}
