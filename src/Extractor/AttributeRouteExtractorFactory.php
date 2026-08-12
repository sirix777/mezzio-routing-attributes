<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Extractor;

use Psr\Container\ContainerInterface;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;

final class AttributeRouteExtractorFactory
{
    public function __invoke(ContainerInterface $container): AttributeRouteExtractor
    {
        $config     = $container->get(RoutingAttributesConfig::class);
        $reader     = new RouteAttributeReader();

        return new AttributeRouteExtractor(
            new ClassEligibilityValidator('callable' === $config->handlersMode),
            $reader,
            new RouteDefinitionBuilder(
                $reader,
                new MethodSignatureValidator(),
                new RouteDataNormalizer()
            )
        );
    }
}
