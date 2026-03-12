<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Exception;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function sprintf;

final class InvalidServiceDefinitionException extends InvalidConfigurationException
{
    public static function invalidMiddlewareServiceType(string $serviceName, string $actualType): self
    {
        return new self(sprintf(
            'Container service "%s" must implement %s or %s; received %s.',
            $serviceName,
            MiddlewareInterface::class,
            RequestHandlerInterface::class,
            $actualType
        ));
    }

    public static function missingMethod(string $serviceName, string $methodName): self
    {
        return new self(sprintf(
            'Container service "%s" must define method "%s".',
            $serviceName,
            $methodName
        ));
    }

    public static function nonPublicMethod(string $className, string $methodName): self
    {
        return new self(sprintf(
            'Method "%s::%s" must be public.',
            $className,
            $methodName
        ));
    }

    public static function invalidMethodReturnType(string $className, string $methodName): self
    {
        return new self(sprintf(
            'Method "%s::%s" must return %s.',
            $className,
            $methodName,
            ResponseInterface::class
        ));
    }
}
