<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes;

use Mezzio\Router\RouteCollectorInterface;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteRegistrarCacheInterface;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveredClassesResolverInterface;
use Sirix\Mezzio\Routing\Attributes\Discovery\NullDiscoveredClassesResolver;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;

final readonly class AttributeRouteProvider
{
    public const DUPLICATE_STRATEGY_THROW  = DuplicateRouteResolver::STRATEGY_THROW;
    public const DUPLICATE_STRATEGY_IGNORE = DuplicateRouteResolver::STRATEGY_IGNORE;

    private RouteDefinitionResolver $routeDefinitionResolver;

    /**
     * @param list<string> $classes
     */
    public function __construct(
        AttributeRouteExtractorInterface $extractor,
        private array $classes,
        DuplicateRouteResolver $duplicateRouteResolver,
        private MiddlewarePipelineFactory $middlewarePipelineFactory,
        private RouteRegistrarCacheInterface $routeRegistrarCache,
        DiscoveredClassesResolverInterface $discoveredClassesResolver = new NullDiscoveredClassesResolver(),
        private RouteRegistrar $routeRegistrar = new RouteRegistrar()
    ) {
        $this->routeDefinitionResolver = new RouteDefinitionResolver(
            $extractor,
            $duplicateRouteResolver,
            $discoveredClassesResolver
        );
    }

    public function registerRoutes(RouteCollectorInterface $collector): void
    {
        if ($this->routeRegistrarCache->registerRoutes($collector, $this->middlewarePipelineFactory)) {
            return;
        }

        $routes = $this->resolveRoutes();
        $this->routeRegistrarCache->save($routes);

        $this->routeRegistrar->register($collector, $routes, $this->middlewarePipelineFactory);
    }

    /**
     * @return list<RouteDefinition>
     */
    private function resolveRoutes(): array
    {
        return $this->routeDefinitionResolver->resolve($this->classes);
    }
}
