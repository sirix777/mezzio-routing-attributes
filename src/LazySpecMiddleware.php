<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidServiceDefinitionException;
use Sirix\Mezzio\Routing\Contracts\MiddlewareFactoryInterface;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;

use function get_debug_type;

final class LazySpecMiddleware implements MiddlewareInterface
{
    private ?MiddlewareInterface $resolved = null;

    /**
     * @param class-string<MiddlewareFactoryInterface> $factoryClass
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly string $factoryClass,
        private readonly MiddlewareSpecification $specification
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (! $this->resolved instanceof MiddlewareInterface) {
            $factory = $this->container->get($this->factoryClass);
            if (! $factory instanceof MiddlewareFactoryInterface) {
                throw InvalidServiceDefinitionException::invalidMiddlewareFactoryServiceType(
                    $this->factoryClass,
                    get_debug_type($factory)
                );
            }

            $this->resolved = $factory->create($this->container, $this->specification);
        }

        return $this->resolved->process($request, $handler);
    }
}
