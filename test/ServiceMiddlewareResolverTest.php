<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\MethodInvokerMiddleware;
use Sirix\Mezzio\Routing\Attributes\RequestHandlerMiddleware;
use Sirix\Mezzio\Routing\Attributes\ServiceMiddlewareResolver;

final class ServiceMiddlewareResolverTest extends TestCase
{
    private ServiceMiddlewareResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ServiceMiddlewareResolver();
    }

    public function testReturnsServiceDirectlyIfItIsMiddlewareAndMethodIsProcess(): void
    {
        $service = $this->createMock(MiddlewareInterface::class);
        $result = $this->resolver->resolve('service', $service, 'process');

        self::assertSame($service, $result);
    }

    public function testReturnsRequestHandlerMiddlewareIfServiceIsRequestHandlerAndMethodIsHandle(): void
    {
        $service = $this->createMock(RequestHandlerInterface::class);
        $result = $this->resolver->resolve('service', $service, 'handle');

        self::assertInstanceOf(RequestHandlerMiddleware::class, $result);
    }

    public function testReturnsMethodInvokerMiddlewareForRegularClassIfMethodIsProcess(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $service = new class($response) {
            public function __construct(private readonly ResponseInterface $response) {}

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $this->response;
            }
        };

        $result = $this->resolver->resolve('service', $service, 'process');

        self::assertInstanceOf(MethodInvokerMiddleware::class, $result);
    }

    public function testReturnsMethodInvokerMiddlewareIfServiceIsMiddlewareButMethodIsNotProcess(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $service = new class($response) implements MiddlewareInterface {
            public function __construct(private readonly ResponseInterface $response) {}

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $this->response;
            }

            public function custom(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };

        $result = $this->resolver->resolve('service', $service, 'custom');

        self::assertInstanceOf(MethodInvokerMiddleware::class, $result);

        // Verification of execution:
        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $result->process($request, $handler);
    }
}
