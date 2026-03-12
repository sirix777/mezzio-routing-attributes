<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\TestAsset;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

use function array_key_exists;
use function sprintf;

final readonly class InMemoryContainer implements ContainerInterface
{
    /**
     * @param array<string, mixed> $services
     */
    public function __construct(private array $services) {}

    public function get(string $id): mixed
    {
        if (! $this->has($id)) {
            throw new class(sprintf('Service "%s" was not found.', $id)) extends RuntimeException implements NotFoundExceptionInterface {};
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }
}
