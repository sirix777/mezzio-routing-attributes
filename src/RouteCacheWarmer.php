<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes;

use Sirix\Mezzio\Routing\Attributes\Cache\RouteRegistrarCacheInterface;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveredClassesResolverInterface;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;

use function is_dir;

/**
 * Builds a configured route cache without needing to boot an application or register routes.
 *
 * @internal
 */
final readonly class RouteCacheWarmer
{
    private RouteDefinitionResolver $routeDefinitionResolver;

    public function __construct(
        private RoutingAttributesConfig $config,
        AttributeRouteExtractorInterface $extractor,
        DuplicateRouteResolver $duplicateRouteResolver,
        DiscoveredClassesResolverInterface $discoveredClassesResolver,
        private RouteRegistrarCacheInterface $routeRegistrarCache
    ) {
        $this->routeDefinitionResolver = new RouteDefinitionResolver(
            $extractor,
            $duplicateRouteResolver,
            $discoveredClassesResolver
        );
    }

    public function warm(): bool
    {
        $this->assertConfiguredDiscoveryPathsExist();

        return $this->routeRegistrarCache->save($this->routeDefinitionResolver->resolve(
            $this->config->classes,
            $this->config->discoveryEnabled
        ));
    }

    private function assertConfiguredDiscoveryPathsExist(): void
    {
        if (! $this->config->discoveryEnabled) {
            return;
        }

        foreach ($this->config->discoveryPaths as $path) {
            if (! is_dir($path)) {
                throw InvalidConfigurationException::missingDiscoveryPath($path);
            }
        }
    }
}
