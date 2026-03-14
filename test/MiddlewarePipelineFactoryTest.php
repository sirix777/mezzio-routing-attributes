<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\MiddlewarePipelineFactory;
use Sirix\Mezzio\Routing\Attributes\ServiceMiddlewareResolver;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\InMemoryContainer;

use function in_array;

final class MiddlewarePipelineFactoryTest extends TestCase
{
    public function testReusesCompiledMiddlewareForSameSignature(): void
    {
        $factory = new MiddlewarePipelineFactory(new InMemoryContainer([
            'mw.first' => new class implements MiddlewareInterface {
                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                {
                    return $handler->handle($request);
                }
            },
            'handler.service' => new class implements MiddlewareInterface {
                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                {
                    return $handler->handle($request);
                }
            },
        ]));

        $first = $factory->createFromCompiled('handler.service', 'process', ['mw.first']);
        $second = $factory->createFromCompiled('handler.service', 'process', ['mw.first']);

        self::assertSame($first, $second);
    }

    public function testLazyPipelineResolvesContainerServicesOnlyOnFirstExecution(): void
    {
        $firstMiddleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };
        $handlerMiddleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };
        $container = new class($firstMiddleware, $handlerMiddleware) implements ContainerInterface {
            public int $getCalls = 0;

            public function __construct(
                private readonly MiddlewareInterface $firstMiddleware,
                private readonly MiddlewareInterface $handlerMiddleware
            ) {}

            public function get(string $id): mixed
            {
                ++$this->getCalls;

                return match ($id) {
                    'mw.first' => $this->firstMiddleware,
                    'handler.service' => $this->handlerMiddleware,
                    default => throw new RuntimeException('Unexpected service id: ' . $id),
                };
            }

            public function has(string $id): bool
            {
                return in_array($id, ['mw.first', 'handler.service'], true);
            }
        };
        $factory = new MiddlewarePipelineFactory($container, new ServiceMiddlewareResolver());
        $pipeline = $factory->createFromCompiled('handler.service', 'process', ['mw.first']);

        self::assertSame(0, $container->getCalls);

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $terminal = new class($response) implements RequestHandlerInterface {
            public function __construct(private readonly ResponseInterface $response) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        $pipeline->process($request, $terminal);
        $pipeline->process($request, $terminal);

        self::assertSame(2, $container->getCalls);
    }
}
