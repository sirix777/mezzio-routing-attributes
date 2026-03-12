<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Extractor;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidMiddlewareClassException;

use function class_exists;
use function is_subclass_of;

final readonly class ClassEligibilityValidator
{
    public function __construct(private bool $allowNonPsr15MethodRoutes = false) {}

    public function assertClassExists(string $className, int|string $index): void
    {
        if ('' === $className) {
            throw InvalidMiddlewareClassException::invalidClassEntry($index, $className);
        }

        if (! class_exists($className)) {
            throw InvalidMiddlewareClassException::nonExistent($className);
        }
    }

    public function assertMiddlewareClass(string $className, bool $hasMethodRoutes): void
    {
        if ($hasMethodRoutes && $this->allowNonPsr15MethodRoutes) {
            return;
        }

        if (
            ! is_subclass_of($className, MiddlewareInterface::class)
            && ! is_subclass_of($className, RequestHandlerInterface::class)
        ) {
            throw InvalidMiddlewareClassException::notMiddleware($className);
        }
    }
}
