<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Factory;

use Psr\Container\ContainerInterface;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;
use Sirix\Mezzio\Routing\Attributes\DuplicateRouteResolver;

final class DuplicateRouteResolverFactory
{
    public function __invoke(ContainerInterface $container): DuplicateRouteResolver
    {
        $rootConfig = $container->has('config') ? $container->get('config') : [];
        $config = RoutingAttributesConfig::fromRootConfig($rootConfig);

        return $this->createFromStrategy($config->duplicateStrategy);
    }

    /**
     * @param 'ignore'|'throw' $strategy
     */
    public function createFromStrategy(string $strategy): DuplicateRouteResolver
    {
        return new DuplicateRouteResolver($strategy);
    }
}
