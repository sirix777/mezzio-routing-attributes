<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Factory;

use Psr\Container\ContainerInterface;
use Sirix\Mezzio\Routing\Attributes\MiddlewarePipelineFactory;
use Sirix\Mezzio\Routing\Attributes\ServiceMiddlewareResolver;

final class MiddlewarePipelineFactoryFactory
{
    public function __invoke(ContainerInterface $container): MiddlewarePipelineFactory
    {
        return new MiddlewarePipelineFactory($container, new ServiceMiddlewareResolver());
    }
}
