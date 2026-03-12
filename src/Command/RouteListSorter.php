<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Command;

use Mezzio\Router\Route;

use function in_array;
use function is_string;
use function strtolower;
use function usort;

final class RouteListSorter
{
    /**
     * @param list<Route> $routes
     *
     * @return list<Route>
     */
    public function sort(array $routes, mixed $sortOrder): array
    {
        $sortOrder = is_string($sortOrder) ? strtolower($sortOrder) : 'name';
        $sortOrder = in_array($sortOrder, ['name', 'path'], true) ? $sortOrder : 'name';

        if ('name' === $sortOrder) {
            usort($routes, fn (Route $a, Route $b): int => $a->getName() <=> $b->getName());

            return $routes;
        }

        usort($routes, fn (Route $a, Route $b): int => $a->getPath() <=> $b->getPath());

        return $routes;
    }
}
