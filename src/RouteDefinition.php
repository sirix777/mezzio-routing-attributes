<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes;

use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;

final readonly class RouteDefinition
{
    /**
     * @param non-empty-string                               $path
     * @param null|list<non-empty-string>                    $methods
     * @param non-empty-string                               $handlerService
     * @param non-empty-string                               $handlerMethod
     * @param list<MiddlewareSpecification|non-empty-string> $middlewareServices
     * @param null|non-empty-string                          $name
     * @param array<string, mixed>                           $defaults
     */
    public function __construct(
        public string $path,
        public ?array $methods,
        public string $handlerService,
        public string $handlerMethod,
        public array $middlewareServices,
        public ?string $name = null,
        public array $defaults = []
    ) {}
}
