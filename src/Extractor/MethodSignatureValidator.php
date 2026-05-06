<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Extractor;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidRouteDefinitionException;

use function is_a;

final readonly class MethodSignatureValidator
{
    public function validate(ReflectionMethod $method, string $className): void
    {
        if (! $method->isPublic()) {
            throw InvalidRouteDefinitionException::nonPublicMethod($className, $method->getName());
        }

        $parameters = $method->getParameters();
        if (! $method->isVariadic()) {
            if (0 === $method->getNumberOfParameters() || $method->getNumberOfRequiredParameters() > 1) {
                throw InvalidRouteDefinitionException::invalidMethodSignature($className, $method->getName());
            }
        } elseif ($method->getNumberOfRequiredParameters() > 1) {
            throw InvalidRouteDefinitionException::invalidMethodSignature($className, $method->getName());
        }

        if ([] !== $parameters) {
            $firstParameter = $parameters[0];
            if (! $this->supportsServerRequestType($firstParameter->getType())) {
                throw InvalidRouteDefinitionException::invalidMethodParameterType(
                    $className,
                    $method->getName(),
                    $firstParameter->getName()
                );
            }
        }

        if (! $this->supportsResponseReturnType($method->getReturnType())) {
            throw InvalidRouteDefinitionException::invalidMethodReturnType($className, $method->getName());
        }
    }

    private function supportsServerRequestType(?ReflectionType $type): bool
    {
        if (! $type instanceof ReflectionType) {
            return true;
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $part) {
                if ($this->supportsServerRequestType($part)) {
                    return true;
                }
            }

            return false;
        }

        if ($type instanceof ReflectionIntersectionType) {
            foreach ($type->getTypes() as $part) {
                if (! $part instanceof ReflectionNamedType || ! $this->supportsServerRequestNamedType($part)) {
                    return false;
                }
            }

            return true;
        }

        if (! $type instanceof ReflectionNamedType) {
            return false;
        }

        return $this->supportsServerRequestNamedType($type);
    }

    private function supportsResponseReturnType(?ReflectionType $type): bool
    {
        if (! $type instanceof ReflectionType) {
            return true;
        }

        if ($type instanceof ReflectionUnionType) {
            if ($type->allowsNull()) {
                return false;
            }

            foreach ($type->getTypes() as $part) {
                if (! $this->supportsResponseReturnType($part)) {
                    return false;
                }
            }

            return true;
        }

        if ($type instanceof ReflectionIntersectionType) {
            $hasResponseConstraint = false;
            foreach ($type->getTypes() as $part) {
                if (! $part instanceof ReflectionNamedType) {
                    return false;
                }

                if ($part->isBuiltin()) {
                    return false;
                }

                if ($this->isResponseCompatibleNamedType($part)) {
                    $hasResponseConstraint = true;
                }
            }

            return $hasResponseConstraint;
        }

        if (! $type instanceof ReflectionNamedType) {
            return false;
        }

        if ($type->allowsNull()) {
            return false;
        }

        return $this->isResponseCompatibleNamedType($type);
    }

    private function supportsServerRequestNamedType(ReflectionNamedType $type): bool
    {
        if ('mixed' === $type->getName() || 'object' === $type->getName()) {
            return true;
        }

        if ($type->isBuiltin()) {
            return false;
        }

        return is_a(ServerRequestInterface::class, $type->getName(), true);
    }

    private function isResponseCompatibleNamedType(ReflectionNamedType $type): bool
    {
        if ('mixed' === $type->getName() || 'object' === $type->getName()) {
            return true;
        }

        if ($type->isBuiltin()) {
            return false;
        }

        return is_a($type->getName(), ResponseInterface::class, true);
    }
}
