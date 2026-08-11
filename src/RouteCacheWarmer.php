<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes;

use Sirix\Mezzio\Routing\Attributes\Cache\RouteRegistrarCacheInterface;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveredClassesResolverInterface;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;

use function array_merge;
use function array_unique;
use function array_values;
use function is_dir;

/**
 * Builds a configured route cache without needing to boot an application or register routes.
 *
 * @internal
 */
final readonly class RouteCacheWarmer
{
    public function __construct(
        private RoutingAttributesConfig $config,
        private AttributeRouteExtractorInterface $extractor,
        private DuplicateRouteResolver $duplicateRouteResolver,
        private DiscoveredClassesResolverInterface $discoveredClassesResolver,
        private RouteRegistrarCacheInterface $routeRegistrarCache
    ) {}

    public function warm(): bool
    {
        $this->assertConfiguredDiscoveryPathsExist();

        $classes = array_values(array_unique(array_merge(
            $this->config->classes,
            $this->config->discoveryEnabled ? $this->discoveredClassesResolver->resolve() : []
        )));

        return $this->routeRegistrarCache->save(
            $this->duplicateRouteResolver->resolve($this->extractor->extract($classes))
        );
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
