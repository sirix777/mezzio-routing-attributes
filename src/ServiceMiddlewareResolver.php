<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionMethod;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidServiceDefinitionException;

use function get_debug_type;
use function is_object;
use function method_exists;

final class ServiceMiddlewareResolver
{
    public function resolve(string $serviceName, mixed $service, string $methodName): MiddlewareInterface
    {
        if ($service instanceof MiddlewareInterface && 'process' === $methodName) {
            return $service;
        }

        if ($service instanceof RequestHandlerInterface && 'handle' === $methodName) {
            return new RequestHandlerMiddleware($service);
        }

        return $this->createMethodMiddleware($serviceName, $service, $methodName);
    }

    private function createMethodMiddleware(string $serviceName, mixed $service, string $methodName): MiddlewareInterface
    {
        if (! is_object($service)) {
            throw InvalidServiceDefinitionException::invalidMiddlewareServiceType($serviceName, get_debug_type($service));
        }

        if (! method_exists($service, $methodName)) {
            throw InvalidServiceDefinitionException::missingMethod($serviceName, $methodName);
        }

        $reflection = new ReflectionMethod($service, $methodName);
        if (! $reflection->isPublic()) {
            throw InvalidServiceDefinitionException::nonPublicMethod($service::class, $methodName);
        }

        return new MethodInvokerMiddleware($service, $methodName);
    }
}
