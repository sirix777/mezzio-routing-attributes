<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes;

use Psr\Container\ContainerInterface;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveryClassMapResolver;
use Sirix\Mezzio\Routing\Attributes\Discovery\RoutableClassFilter;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;

use function array_merge;
use function array_unique;
use function array_values;

final class AttributeRouteProviderFactory
{
    /** @var null|callable(RoutingAttributesConfig): DiscoveryClassMapResolver */
    private $discoveryResolverFactory;

    /** @param null|callable(RoutingAttributesConfig): DiscoveryClassMapResolver $discoveryResolverFactory */
    public function __construct(?callable $discoveryResolverFactory = null)
    {
        $this->discoveryResolverFactory = $discoveryResolverFactory;
    }

    public function __invoke(ContainerInterface $container): AttributeRouteProvider
    {
        $rootConfig = $container->has('config') ? $container->get('config') : [];
        $config = RoutingAttributesConfig::fromRootConfig($rootConfig);
        $compiledRouteRegistrarCache = $config->cacheEnabled
            ? new CompiledRouteRegistrarCache($config->cacheFile)
            : null;

        $classes = $config->classes;
        if ($config->discoveryEnabled && ! $this->shouldSkipDiscovery($compiledRouteRegistrarCache)) {
            $discoveredClasses = $this->discoveryResolver($config)->resolve();
            $classes = array_values(array_unique(array_merge($classes, $discoveredClasses)));
        }

        return new AttributeRouteProvider(
            $container,
            $container->get(AttributeRouteExtractorInterface::class),
            $classes,
            $config->duplicateStrategy,
            new DuplicateRouteResolver($config->duplicateStrategy),
            new MiddlewarePipelineFactory($container),
            $compiledRouteRegistrarCache
        );
    }

    private function shouldSkipDiscovery(?CompiledRouteRegistrarCache $compiledRouteRegistrarCache): bool
    {
        return $compiledRouteRegistrarCache instanceof CompiledRouteRegistrarCache
            && $compiledRouteRegistrarCache->hasUsableArtifact();
    }

    private function discoveryResolver(RoutingAttributesConfig $config): DiscoveryClassMapResolver
    {
        if (null !== $this->discoveryResolverFactory) {
            return ($this->discoveryResolverFactory)($config);
        }

        return new DiscoveryClassMapResolver(
            paths: $config->discoveryPaths,
            strategy: $config->discoveryStrategy,
            psr4Mappings: $config->discoveryPsr4Mappings,
            psr4FallbackToToken: $config->discoveryPsr4FallbackToToken,
            routableClassFilter: new RoutableClassFilter('callable' === $config->handlersMode)
        );
    }
}
