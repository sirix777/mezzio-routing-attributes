<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Config;

use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;

use function array_values;
use function is_array;
use function is_string;

final readonly class ClassesConfigParser
{
    /**
     * @param array<string, mixed> $routingAttributesConfig
     *
     * @return list<non-empty-string>
     */
    public function parse(array $routingAttributesConfig): array
    {
        $classes = $routingAttributesConfig['classes'] ?? [];
        if (! is_array($classes)) {
            throw InvalidConfigurationException::invalidClassesConfiguration($classes);
        }

        foreach ($classes as $index => $className) {
            if (! is_string($className) || '' === $className) {
                throw InvalidConfigurationException::invalidClassEntry($index, $className);
            }
        }

        return array_values($classes);
    }
}
