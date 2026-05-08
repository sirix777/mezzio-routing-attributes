<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes;

use Mezzio\Router\RouteCollectorInterface;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteRegistrarCacheInterface;
use Sirix\Mezzio\Routing\Attributes\Command\RouteMiddlewareDisplayResolver;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;

use function trim;

final readonly class AttributeRouteProvider
{
    public const DUPLICATE_STRATEGY_THROW = DuplicateRouteResolver::STRATEGY_THROW;
    public const DUPLICATE_STRATEGY_IGNORE = DuplicateRouteResolver::STRATEGY_IGNORE;

    /**
     * @param list<string> $classes
     */
    public function __construct(
        private AttributeRouteExtractorInterface $extractor,
        private array $classes,
        private DuplicateRouteResolver $duplicateRouteResolver,
        private MiddlewarePipelineFactory $middlewarePipelineFactory,
        private RouteRegistrarCacheInterface $routeRegistrarCache
    ) {}

    public function registerRoutes(RouteCollectorInterface $collector): void
    {
        if ($this->routeRegistrarCache->registerRoutes($collector, $this->middlewarePipelineFactory)) {
            return;
        }

        $routes = $this->resolveRoutes();
        $this->routeRegistrarCache->save($routes);

        foreach ($routes as $route) {
            $pipeline = $this->middlewarePipelineFactory->create($route);
            $registeredRoute = $collector->route(
                $route->path,
                $pipeline['middleware'],
                $route->methods,
                $this->normalizeRouteName($route->name)
            );
            $options = $registeredRoute->getOptions();
            $options[RouteMiddlewareDisplayResolver::ROUTE_OPTION_MIDDLEWARE_DISPLAY] = $pipeline['middlewareDisplay'];
            if ([] !== $route->defaults) {
                $options = [...$options, ...$route->defaults];
            }
            $registeredRoute->setOptions($options);
        }
    }

    /**
     * @return list<RouteDefinition>
     */
    private function resolveRoutes(): array
    {
        return $this->duplicateRouteResolver->resolve($this->extractor->extract($this->classes));
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
