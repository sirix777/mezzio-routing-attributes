<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes;

use ArrayObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\MiddlewarePipelineFactory;
use Sirix\Mezzio\Routing\Attributes\ServiceMiddlewareResolver;
use Sirix\Mezzio\Routing\Contracts\Exception\InvalidMiddlewareSpecificationException;
use Sirix\Mezzio\Routing\Contracts\MiddlewareFactoryInterface;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\InMemoryContainer;

use function in_array;

final class MiddlewarePipelineFactoryTest extends TestCase
{
    public function testReusesCompiledMiddlewareForSameSignature(): void
    {
        $factory = new MiddlewarePipelineFactory(new InMemoryContainer([
            'mw.first'        => new class implements MiddlewareInterface {
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
        ]), new ServiceMiddlewareResolver());

        $first  = $factory->createFromSignature('handler.service', 'process', ['mw.first']);
        $second = $factory->createFromSignature('handler.service', 'process', ['mw.first']);

        self::assertSame($first, $second);
    }

    public function testUncachedCompiledPipelineDoesNotChangeColdOrCachedPipelineReuse(): void
    {
        $factory = new MiddlewarePipelineFactory(new InMemoryContainer([
            'mw.first'        => new class implements MiddlewareInterface {
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
        ]), new ServiceMiddlewareResolver());

        $generatedArtifactPipeline = $factory->createUncachedFromSignature('handler.service', 'process', ['mw.first']);
        $coldPipeline              = $factory->createFromSignature('handler.service', 'process', ['mw.first']);
        $cachedPipeline            = $factory->createFromSignature('handler.service', 'process', ['mw.first']);

        self::assertNotSame($generatedArtifactPipeline, $coldPipeline);
        self::assertSame($coldPipeline, $cachedPipeline);
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
                    'mw.first'        => $this->firstMiddleware,
                    'handler.service' => $this->handlerMiddleware,
                    default           => throw new RuntimeException('Unexpected service id: ' . $id),
                };
            }

            public function has(string $id): bool
            {
                return in_array($id, ['mw.first', 'handler.service'], true);
            }
        };
        $factory  = new MiddlewarePipelineFactory($container, new ServiceMiddlewareResolver());
        $pipeline = $factory->createFromSignature('handler.service', 'process', ['mw.first']);

        self::assertSame(0, $container->getCalls);

        $request  = $this->createMock(ServerRequestInterface::class);
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

    public function testResolvesMixedStringAndSpecificationEntriesInOrder(): void
    {
        $events         = new ArrayObject();
        $specMiddleware = new class($events) implements MiddlewareInterface {
            /** @param ArrayObject<int, string> $events */
            public function __construct(private readonly ArrayObject $events) {}

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->events->append('spec');

                return $handler->handle($request);
            }
        };
        $specFactory = new class($specMiddleware) implements MiddlewareFactoryInterface {
            public int $createCalls = 0;

            public function __construct(private readonly MiddlewareInterface $middleware) {}

            public function create(ContainerInterface $container, MiddlewareSpecification $specification): MiddlewareInterface
            {
                ++$this->createCalls;

                return $this->middleware;
            }
        };
        $container   = new InMemoryContainer([
            'middleware.string' => new class($events) implements MiddlewareInterface {
                /** @param ArrayObject<int, string> $events */
                public function __construct(private readonly ArrayObject $events) {}

                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                {
                    $this->events->append('string');

                    return $handler->handle($request);
                }
            },
            $specFactory::class => $specFactory,
            'handler.service'   => new class($events) implements MiddlewareInterface {
                /** @param ArrayObject<int, string> $events */
                public function __construct(private readonly ArrayObject $events) {}

                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                {
                    $this->events->append('handler');

                    return $handler->handle($request);
                }
            },
        ]);
        $factory       = new MiddlewarePipelineFactory($container, new ServiceMiddlewareResolver());
        $specification = new MiddlewareSpecification('middleware.spec', $specFactory::class, [
            'profile' => 'admin',
        ]);
        $pipeline      = $factory->createFromSignature('handler.service', 'process', ['middleware.string', $specification]);
        $samePipeline  = $factory->createFromSignature('handler.service', 'process', ['middleware.string', $specification]);
        $response      = $this->createMock(ResponseInterface::class);
        $terminal      = new class($response) implements RequestHandlerInterface {
            public function __construct(private readonly ResponseInterface $response) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        self::assertSame($pipeline, $samePipeline);
        self::assertSame($response, $pipeline->process($this->createMock(ServerRequestInterface::class), $terminal));
        self::assertSame(['string', 'spec', 'handler'], $events->getArrayCopy());
        self::assertSame(1, $specFactory->createCalls);
    }

    public function testRejectsSpecificationWithoutFactory(): void
    {
        $factory = new MiddlewarePipelineFactory(new InMemoryContainer([]), new ServiceMiddlewareResolver());

        $this->expectException(InvalidMiddlewareSpecificationException::class);

        $factory->createFromSignature('handler.service', 'process', [new MiddlewareSpecification('middleware.spec')]);
    }

    public function testDoesNotReusePipelineForSpecificationAndEquivalentDelimitedStringEntries(): void
    {
        $factory       = new MiddlewarePipelineFactory(new InMemoryContainer([]), new ServiceMiddlewareResolver());
        $specification = new MiddlewareSpecification('profile', 'factory.id', []);

        $specificationPipeline = $factory->createFromSignature('handler.service', 'process', [$specification]);
        $stringPipeline        = $factory->createFromSignature(
            'handler.service',
            'process',
            ['profile', 'factory.id', 'a:0:{}']
        );

        self::assertNotSame($specificationPipeline, $stringPipeline);
    }

    public function testDoesNotReusePipelineForSpecificationsWithAmbiguousInnerSignatures(): void
    {
        $factory = new MiddlewarePipelineFactory(new InMemoryContainer([]), new ServiceMiddlewareResolver());

        $firstPipeline = $factory->createFromSignature('handler.service', 'process', [
            new MiddlewareSpecification("profile\0tenant", 'factory.id', []),
        ]);
        $secondPipeline = $factory->createFromSignature('handler.service', 'process', [
            new MiddlewareSpecification('profile', "tenant\0factory.id", []),
        ]);

        self::assertNotSame($firstPipeline, $secondPipeline);
    }
}
