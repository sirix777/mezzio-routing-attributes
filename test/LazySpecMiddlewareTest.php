<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes;

use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidServiceDefinitionException;
use Sirix\Mezzio\Routing\Attributes\LazySpecMiddleware;
use Sirix\Mezzio\Routing\Contracts\MiddlewareFactoryInterface;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\InMemoryContainer;
use stdClass;

final class LazySpecMiddlewareTest extends TestCase
{
    public function testResolvesFactoryOnceAndDelegatesWithOriginalContainerAndSpecification(): void
    {
        $response    = $this->createMock(ResponseInterface::class);
        $middleware  = new class implements MiddlewareInterface {
            public int $processCalls = 0;

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                ++$this->processCalls;

                return $handler->handle($request);
            }
        };
        $factory     = new class($middleware) implements MiddlewareFactoryInterface {
            public int $createCalls = 0;

            public ?ContainerInterface $receivedContainer = null;

            public ?MiddlewareSpecification $receivedSpecification = null;

            public function __construct(private readonly MiddlewareInterface $middleware) {}

            public function create(ContainerInterface $container, MiddlewareSpecification $specification): MiddlewareInterface
            {
                ++$this->createCalls;
                $this->receivedContainer     = $container;
                $this->receivedSpecification = $specification;

                return $this->middleware;
            }
        };
        $container   = new InMemoryContainer([
            $factory::class => $factory,
        ]);
        $specification = new MiddlewareSpecification('middleware.service', $factory::class, [
            'scope' => 'admin',
        ]);
        $lazy          = new LazySpecMiddleware($container, $factory::class, $specification);
        $handler       = new class($response) implements RequestHandlerInterface {
            public function __construct(private readonly ResponseInterface $response) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
        $request = $this->createMock(ServerRequestInterface::class);

        self::assertSame($response, $lazy->process($request, $handler));
        self::assertSame($response, $lazy->process($request, $handler));
        self::assertSame(1, $factory->createCalls);
        self::assertSame(2, $middleware->processCalls);
        self::assertSame($container, $factory->receivedContainer);
        self::assertSame($specification, $factory->receivedSpecification);
    }

    public function testRejectsContainerServiceThatIsNotAMiddlewareFactory(): void
    {
        $declaredFactory = new class implements MiddlewareFactoryInterface {
            public function create(ContainerInterface $container, MiddlewareSpecification $specification): MiddlewareInterface
            {
                throw new LogicException('This factory must not be called.');
            }
        };
        $specification   = new MiddlewareSpecification('middleware.service', $declaredFactory::class);
        $lazy            = new LazySpecMiddleware(
            new InMemoryContainer([
                $declaredFactory::class => new stdClass(),
            ]),
            $declaredFactory::class,
            $specification
        );

        $this->expectException(InvalidServiceDefinitionException::class);
        $this->expectExceptionMessage(MiddlewareFactoryInterface::class);

        $lazy->process(
            $this->createMock(ServerRequestInterface::class),
            $this->createMock(RequestHandlerInterface::class)
        );
    }
}
