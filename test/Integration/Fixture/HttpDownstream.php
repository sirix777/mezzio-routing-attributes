<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Integration\Fixture;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class HttpDownstream implements RequestHandlerInterface
{
    /** @var list<ServerRequestInterface> */
    public array $requests = [];

    public function __construct(private readonly string $label) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        return new JsonResponse([
            'downstream' => $this->label,
            'service'    => $request->getAttribute('service'),
            'action'     => $request->getAttribute('action'),
        ], 404);
    }
}
