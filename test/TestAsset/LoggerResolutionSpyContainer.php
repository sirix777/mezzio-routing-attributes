<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\TestAsset;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Config\RoutingAttributesConfig;

use function array_key_exists;
use function sprintf;

final class LoggerResolutionSpyContainer implements ContainerInterface
{
    /** @var list<string> */
    public array $resolutions = [];

    /**
     * @param array<string, mixed> $services
     */
    public function __construct(private array $services) {}

    public function get(string $id): mixed
    {
        $this->resolutions[] = 'get:' . $id;

        if (RoutingAttributesConfig::class === $id && ! $this->has($id) && $this->has('config')) {
            $this->services[$id] = RoutingAttributesConfig::fromRootConfig($this->services['config']);

            return $this->services[$id];
        }

        if (! $this->has($id)) {
            throw new class(sprintf('Service "%s" was not found.', $id)) extends RuntimeException implements NotFoundExceptionInterface {};
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        $this->resolutions[] = 'has:' . $id;

        return array_key_exists($id, $this->services);
    }
}
