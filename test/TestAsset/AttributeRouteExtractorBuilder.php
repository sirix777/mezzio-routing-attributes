<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\TestAsset;

use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractor;
use Sirix\Mezzio\Routing\Attributes\Extractor\ClassEligibilityValidator;
use Sirix\Mezzio\Routing\Attributes\Extractor\MethodSignatureValidator;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteAttributeReader;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteDataNormalizer;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteDefinitionBuilder;

final class AttributeRouteExtractorBuilder
{
    public static function create(bool $allowCallable = false): AttributeRouteExtractor
    {
        return new AttributeRouteExtractor(
            new ClassEligibilityValidator($allowCallable),
            new RouteAttributeReader(),
            new RouteDefinitionBuilder(
                new RouteAttributeReader(),
                new MethodSignatureValidator(),
                new RouteDataNormalizer()
            )
        );
    }
}
