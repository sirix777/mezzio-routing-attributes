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
use function hash;
use function implode;

final class AttributeRouteProviderFactory
{
    public function __invoke(ContainerInterface $container): AttributeRouteProvider
    {
        $rootConfig = $container->has('config') ? $container->get('config') : [];
        $config = RoutingAttributesConfig::fromRootConfig($rootConfig);

        $discoveryFingerprint = null;
        $classes = $config->classes;
        if ($config->discoveryEnabled) {
            $classMapCacheFile = $config->discoveryClassMapCacheEnabled
                ? $config->discoveryClassMapCacheFile
                : null;
            $discoveryResult = (new DiscoveryClassMapResolver(
                $config->discoveryPaths,
                $classMapCacheFile,
                $config->discoveryClassMapCacheValidate,
                $config->discoveryClassMapCacheWriteFailStrategy,
                $config->discoveryStrategy,
                $config->discoveryPsr4Mappings,
                $config->discoveryPsr4FallbackToToken,
                null,
                null,
                null,
                new RoutableClassFilter('callable' === $config->handlersMode)
            ))->resolve();
            $classes = array_values(array_unique(array_merge($classes, $discoveryResult['classes'])));
            $discoveryFingerprint = $discoveryResult['fingerprint'];
        }

        $cacheFile = null;
        $cacheMeta = null;
        if ($config->cacheEnabled) {
            $cacheFile = $config->cacheFile;
            $cacheMeta = [
                'format_version' => RouteDefinitionCache::CACHE_FORMAT_VERSION,
                'duplicate_strategy' => $config->duplicateStrategy,
                'classes_fingerprint' => $this->createClassesFingerprint($classes, $config->duplicateStrategy),
            ];
            if (null !== $discoveryFingerprint) {
                $cacheMeta['discovery_fingerprint'] = $discoveryFingerprint;
            }
        }

        return new AttributeRouteProvider(
            $container,
            $container->get(AttributeRouteExtractorInterface::class),
            $classes,
            $config->duplicateStrategy,
            $cacheFile,
            new DuplicateRouteResolver($config->duplicateStrategy),
            new RouteDefinitionCache(
                $cacheFile,
                $cacheMeta,
                $config->cacheStrict,
                $config->cacheWriteFailStrategy
            ),
            new MiddlewarePipelineFactory($container)
        );
    }

    /** @param list<string> $classes */
    private function createClassesFingerprint(array $classes, string $duplicateStrategy): string
    {
        return hash(
            'sha256',
            RouteDefinitionCache::CACHE_FORMAT_VERSION . '|' . $duplicateStrategy . '|' . implode('|', $classes)
        );
    }
}
