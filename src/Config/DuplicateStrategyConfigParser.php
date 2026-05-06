<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Config;

use Sirix\Mezzio\Routing\Attributes\AttributeRouteProvider;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;

use function in_array;
use function is_string;

final readonly class DuplicateStrategyConfigParser
{
    /**
     * @param array<string, mixed> $routingAttributesConfig
     *
     * @return AttributeRouteProvider::DUPLICATE_STRATEGY_IGNORE|AttributeRouteProvider::DUPLICATE_STRATEGY_THROW
     */
    public function parse(array $routingAttributesConfig): string
    {
        $duplicateStrategy = $routingAttributesConfig['duplicate_strategy'] ?? AttributeRouteProvider::DUPLICATE_STRATEGY_THROW;
        if (! is_string($duplicateStrategy) || ! in_array(
            $duplicateStrategy,
            [
                AttributeRouteProvider::DUPLICATE_STRATEGY_THROW,
                AttributeRouteProvider::DUPLICATE_STRATEGY_IGNORE,
            ],
            true
        )) {
            throw InvalidConfigurationException::invalidDuplicateStrategy($duplicateStrategy);
        }

        return $duplicateStrategy;
    }
}
