<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Integration\Fixture;

use Laminas\Diactoros\Response\TextResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/middleware', 'http.middleware')]
final readonly class HttpMiddleware implements MiddlewareInterface
{
    public function __construct(private HttpTrace $trace) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->trace->events[] = 'service:before';
        if ($request->getAttribute('stop', false)) {
            return new TextResponse('stopped', 202);
        }
        $response = $handler->handle($request->withAttribute('service', $request->getAttribute('input')));
        if ($request->getAttribute('repeat', false)) {
            $response = $handler->handle($request->withAttribute('service', 'second'));
        }
        $this->trace->events[] = 'service:after';

        return $response->withHeader('X-Service', 'executed');
    }
}
