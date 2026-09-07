<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Integration\Fixture;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\TestAttributeModifier;

#[Post('/api/', 'ignored.api', ['class.first'])]
#[Get('/', 'ignored.root')]
#[Post('v1/', 'ignored.version', ['class.second'])]
#[TestAttributeModifier(middleware: ['class.modifier'], defaults: [
    'scope'     => 'class',
    'classOnly' => true,
])]
final readonly class HttpPrefixActions
{
    #[Get('orders/', 'orders.list', ['method.route'])]
    #[Get('/', 'orders.root', ['method.route'])]
    #[TestAttributeModifier(middleware: ['method.modifier'], defaults: [
        'scope'      => 'method',
        'methodOnly' => true,
    ])]
    public function orders(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse([
            'path' => $request->getUri()->getPath(),
        ]);
    }
}
