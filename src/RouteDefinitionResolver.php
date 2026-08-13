<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes;

use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveredClassesResolverInterface;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;

use function array_merge;
use function array_unique;
use function array_values;

/**
 * Resolves configured and discovered classes into de-duplicated route definitions.
 *
 * @internal
 */
final readonly class RouteDefinitionResolver
{
    public function __construct(
        private AttributeRouteExtractorInterface $extractor,
        private DuplicateRouteResolver $duplicateRouteResolver,
        private DiscoveredClassesResolverInterface $discoveredClassesResolver
    ) {}

    /**
     * @param list<string> $configuredClasses
     *
     * @return list<RouteDefinition>
     */
    public function resolve(array $configuredClasses, bool $includeDiscoveredClasses = true): array
    {
        $classes = array_values(array_unique(array_merge(
            $configuredClasses,
            $includeDiscoveredClasses ? $this->discoveredClassesResolver->resolve() : []
        )));

        return $this->duplicateRouteResolver->resolve($this->extractor->extract($classes));
    }
}
