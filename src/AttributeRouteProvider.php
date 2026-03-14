<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes;

use Mezzio\Router\RouteCollectorInterface;
use Psr\Container\ContainerInterface;
use Sirix\Mezzio\Routing\Attributes\Command\RouteMiddlewareDisplayResolver;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;

use function trim;

final readonly class AttributeRouteProvider
{
    public const DUPLICATE_STRATEGY_THROW = DuplicateRouteResolver::STRATEGY_THROW;
    public const DUPLICATE_STRATEGY_IGNORE = DuplicateRouteResolver::STRATEGY_IGNORE;

    /**
     * @param list<string>                                                   $classes
     * @param self::DUPLICATE_STRATEGY_IGNORE|self::DUPLICATE_STRATEGY_THROW $duplicateStrategy
     */
    public function __construct(
        private ContainerInterface $container,
        private AttributeRouteExtractorInterface $extractor,
        private array $classes,
        private string $duplicateStrategy = self::DUPLICATE_STRATEGY_THROW,
        private ?DuplicateRouteResolver $duplicateRouteResolver = null,
        private ?MiddlewarePipelineFactory $middlewarePipelineFactory = null,
        private ?CompiledRouteRegistrarCache $compiledRouteRegistrarCache = null
    ) {}

    public function registerRoutes(RouteCollectorInterface $collector): void
    {
        $pipelineFactory = $this->middlewarePipelineFactory();
        if (
            $this->compiledRouteRegistrarCache instanceof CompiledRouteRegistrarCache
            && $this->compiledRouteRegistrarCache->registerRoutes($collector, $pipelineFactory)
        ) {
            return;
        }

        $routes = $this->resolveRoutes();
        if ($this->compiledRouteRegistrarCache instanceof CompiledRouteRegistrarCache) {
            $this->compiledRouteRegistrarCache->save($routes);
        }

        foreach ($routes as $route) {
            $pipeline = $pipelineFactory->create($route);
            $registeredRoute = $collector->route(
                $route->path,
                $pipeline['middleware'],
                $route->methods,
                $this->normalizeRouteName($route->name)
            );
            $options = $registeredRoute->getOptions();
            $options[RouteMiddlewareDisplayResolver::ROUTE_OPTION_MIDDLEWARE_DISPLAY] = $pipeline['middlewareDisplay'];
            $registeredRoute->setOptions($options);
        }
    }

    /**
     * @return list<RouteDefinition>
     */
    private function resolveRoutes(): array
    {
        return $this->duplicateRouteResolver()->resolve($this->extractor->extract($this->classes));
    }

    private function duplicateRouteResolver(): DuplicateRouteResolver
    {
        return $this->duplicateRouteResolver
            ?? new DuplicateRouteResolver($this->duplicateStrategy);
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
