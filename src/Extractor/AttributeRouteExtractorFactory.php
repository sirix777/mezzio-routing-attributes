<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Extractor;

use Psr\Container\ContainerInterface;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;

final class AttributeRouteExtractorFactory
{
    public function __invoke(ContainerInterface $container): AttributeRouteExtractor
    {
        $rootConfig = $container->has('config') ? $container->get('config') : [];
        $config = RoutingAttributesConfig::fromRootConfig($rootConfig);

        return new AttributeRouteExtractor(
            new ClassEligibilityValidator('callable' === $config->handlersMode)
        );
    }
}
