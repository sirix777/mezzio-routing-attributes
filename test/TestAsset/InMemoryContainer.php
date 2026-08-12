<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\TestAsset;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;

use function array_key_exists;
use function sprintf;

final class InMemoryContainer implements ContainerInterface
{
    /**
     * @param array<string, mixed> $services
     */
    public function __construct(private array $services) {}

    public function get(string $id): mixed
    {
        if (RoutingAttributesConfig::class === $id && ! $this->has($id) && $this->has('config')) {
            $this->services[$id] = RoutingAttributesConfig::fromRootConfig($this->services['config']);
        }

        if (! $this->has($id)) {
            throw new class(sprintf('Service "%s" was not found.', $id)) extends RuntimeException implements NotFoundExceptionInterface {};
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }

    public function set(string $id, mixed $service): void
    {
        $this->services[$id] = $service;
    }
}
