<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Exception;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function get_debug_type;
use function sprintf;

final class InvalidMiddlewareClassException extends InvalidConfigurationException
{
    public static function invalidClassEntry(int|string $index, mixed $className): self
    {
        return new self(sprintf(
            'Configuration key "routing_attributes.classes" must contain non-empty strings; received %s at index "%s".',
            get_debug_type($className),
            (string) $index
        ));
    }

    public static function nonExistent(string $className): self
    {
        return new self(sprintf(
            'Configured middleware class "%s" does not exist.',
            $className
        ));
    }

    public static function notMiddleware(string $className): self
    {
        return new self(sprintf(
            'Configured class "%s" must implement %s or %s.',
            $className,
            MiddlewareInterface::class,
            RequestHandlerInterface::class
        ));
    }
}
