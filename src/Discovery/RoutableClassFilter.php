<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Discovery;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function is_subclass_of;

final readonly class RoutableClassFilter
{
    public function __construct(private bool $allowAnyClass = false) {}

    /**
     * @param list<non-empty-string> $classes
     *
     * @return list<non-empty-string>
     */
    public function filter(array $classes): array
    {
        $result = [];
        foreach ($classes as $className) {
            if ($this->allowAnyClass) {
                $result[] = $className;

                continue;
            }

            if (
                ! is_subclass_of($className, MiddlewareInterface::class)
                && ! is_subclass_of($className, RequestHandlerInterface::class)
            ) {
                continue;
            }

            $result[] = $className;
        }

        return $result;
    }
}
