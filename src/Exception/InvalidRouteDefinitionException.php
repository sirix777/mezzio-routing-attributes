<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Exception;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function sprintf;

final class InvalidRouteDefinitionException extends InvalidConfigurationException
{
    public static function emptyPath(string $className): self
    {
        return new self(sprintf(
            'Route path for class "%s" must be a non-empty string.',
            $className
        ));
    }

    public static function emptyName(string $className): self
    {
        return new self(sprintf(
            'Route name for class "%s" must be a non-empty string when provided.',
            $className
        ));
    }

    public static function invalidMethods(string $className): self
    {
        return new self(sprintf(
            'Route methods for class "%s" must contain at least one non-empty HTTP method when provided.',
            $className
        ));
    }

    public static function invalidMiddlewareServices(string $className): self
    {
        return new self(sprintf(
            'Route middleware list for class "%s" must contain at least one non-empty service name when provided.',
            $className
        ));
    }

    public static function nonPublicMethod(string $className, string $methodName): self
    {
        return new self(sprintf(
            'Route method "%s::%s" must be public.',
            $className,
            $methodName
        ));
    }

    public static function invalidMethodSignature(string $className, string $methodName): self
    {
        return new self(sprintf(
            'Route method "%s::%s" must accept a request argument (%s), optionally followed by a handler argument, '
            . 'or be variadic-compatible with both arguments.',
            $className,
            $methodName,
            ServerRequestInterface::class
        ));
    }

    public static function invalidMethodParameterType(string $className, string $methodName, string $parameterName): self
    {
        return new self(sprintf(
            'Route method "%s::%s" has incompatible first parameter "$%s"; it must accept %s.',
            $className,
            $methodName,
            $parameterName,
            ServerRequestInterface::class
        ));
    }

    public static function invalidMethodHandlerParameterType(string $className, string $methodName, string $parameterName): self
    {
        return new self(sprintf(
            'Route method "%s::%s" has incompatible handler parameter "$%s"; the second argument or variadic capture must accept %s.',
            $className,
            $methodName,
            $parameterName,
            RequestHandlerInterface::class
        ));
    }

    public static function invalidMethodReturnType(string $className, string $methodName): self
    {
        return new self(sprintf(
            'Route method "%s::%s" must declare %s-compatible return type (or no declared return type).',
            $className,
            $methodName,
            ResponseInterface::class
        ));
    }
}
