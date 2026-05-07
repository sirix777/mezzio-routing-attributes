<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Factory;

use Psr\Container\ContainerInterface;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveredClassesResolverInterface;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveryClassMapResolver;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveryFileInventory;
use Sirix\Mezzio\Routing\Attributes\Discovery\NullDiscoveredClassesResolver;
use Sirix\Mezzio\Routing\Attributes\Discovery\PhpClassNameParser;
use Sirix\Mezzio\Routing\Attributes\Discovery\Psr4ClassNameResolver;
use Sirix\Mezzio\Routing\Attributes\Discovery\RoutableClassFilter;

final class DiscoveryClassMapResolverFactory
{
    public function __invoke(ContainerInterface $container): DiscoveredClassesResolverInterface
    {
        $rootConfig = $container->has('config') ? $container->get('config') : [];
        $config = RoutingAttributesConfig::fromRootConfig($rootConfig);

        if (! $config->discoveryEnabled) {
            return new NullDiscoveredClassesResolver();
        }

        return new DiscoveryClassMapResolver(
            $config->discoveryStrategy,
            $config->discoveryPsr4FallbackToToken,
            new DiscoveryFileInventory($config->discoveryPaths),
            new PhpClassNameParser(),
            new Psr4ClassNameResolver($config->discoveryPsr4Mappings),
            new RoutableClassFilter('callable' === $config->handlersMode)
        );
    }
}
