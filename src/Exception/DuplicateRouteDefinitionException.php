<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Exception;

use function sprintf;

final class DuplicateRouteDefinitionException extends InvalidConfigurationException
{
    public static function duplicateName(string $routeName): self
    {
        return new self(sprintf(
            'Duplicate route name "%s" detected in attribute route definitions.',
            $routeName
        ));
    }

    public static function duplicatePathAndMethods(string $path, string $methods): self
    {
        return new self(sprintf(
            'Duplicate route path/methods detected for path "%s" and methods "%s".',
            $path,
            $methods
        ));
    }
}
