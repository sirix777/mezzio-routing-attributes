<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Config;

use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;

use function in_array;
use function is_array;
use function is_string;

final readonly class HandlersConfigParser
{
    /**
     * @param array<string, mixed> $routingAttributesConfig
     *
     * @return 'callable'|'psr15'
     */
    public function parse(array $routingAttributesConfig): string
    {
        $handlersConfig = $routingAttributesConfig['handlers'] ?? [];
        if (! is_array($handlersConfig)) {
            throw InvalidConfigurationException::invalidHandlersType($handlersConfig);
        }

        $handlersMode = $handlersConfig['mode'] ?? 'psr15';
        if (! is_string($handlersMode) || ! in_array($handlersMode, ['psr15', 'callable'], true)) {
            throw InvalidConfigurationException::invalidHandlersMode($handlersMode);
        }

        return $handlersMode;
    }
}
