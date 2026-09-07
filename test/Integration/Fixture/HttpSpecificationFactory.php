<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Integration\Fixture;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Contracts\MiddlewareFactoryInterface;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;

final readonly class HttpSpecificationFactory implements MiddlewareFactoryInterface
{
    public function __construct(private HttpTrace $trace) {}

    public function create(ContainerInterface $container, MiddlewareSpecification $specification): MiddlewareInterface
    {
        $this->trace->created['specification-result'] = ($this->trace->created['specification-result'] ?? 0) + 1;

        return new class($this->trace, $specification) implements MiddlewareInterface {
            public function __construct(private readonly HttpTrace $trace, private readonly MiddlewareSpecification $specification) {}

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->trace->events[] = 'specification:before';
                $response              = $handler->handle($request->withAttribute('specification', $this->specification->arguments['value']));
                $this->trace->events[] = 'specification:after';

                return $response->withHeader('X-Specification', 'executed');
            }
        };
    }
}
