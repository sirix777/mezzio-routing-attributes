<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Config;

use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;

use function is_bool;

final readonly class OverrideMezzioRoutesListCommandConfigParser
{
    /**
     * @param array<string, mixed> $routingAttributesConfig
     */
    public function parse(array $routingAttributesConfig): bool
    {
        $override = $routingAttributesConfig['override_mezzio_routes_list_command'] ?? false;
        if (! is_bool($override)) {
            throw InvalidConfigurationException::invalidMezzioRoutesListCommandOverride($override);
        }

        return $override;
    }
}
