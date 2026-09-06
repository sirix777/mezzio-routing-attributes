<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Extractor;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionMethod;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidRouteDefinitionException;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractor;
use Sirix\Mezzio\Routing\Attributes\Extractor\ClassEligibilityValidator;
use Sirix\Mezzio\Routing\Attributes\Extractor\MethodSignatureValidator;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteAttributeReader;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteDataNormalizer;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteDefinitionBuilder;
use Sirix\Mezzio\Routing\Attributes\MethodInvokerMiddleware;
use SirixTest\Mezzio\Routing\Attributes\Extractor\Fixture\CallableActionTrailingVariadic;

final class MethodSignatureValidatorTest extends TestCase
{
    public function testExtractsAndInvokesActionWithTrailingVariadic(): void
    {
        $extractor = new AttributeRouteExtractor(
            new ClassEligibilityValidator(true),
            new RouteAttributeReader(),
            new RouteDefinitionBuilder(
                new RouteAttributeReader(),
                new MethodSignatureValidator(),
                new RouteDataNormalizer()
            )
        );
        $routes = $extractor->extract([CallableActionTrailingVariadic::class]);

        self::assertCount(1, $routes);
        self::assertSame('run', $routes[0]->handlerMethod);
        $this->assertInvokesMethod($routes[0]->handlerMethod);
    }

    #[DataProvider('compatibleMethods')]
    public function testAcceptsAndInvokesCompatibleSignature(string $method): void
    {
        (new MethodSignatureValidator())->validate(
            new ReflectionMethod(CallableActionTrailingVariadic::class, $method),
            CallableActionTrailingVariadic::class
        );

        $this->assertInvokesMethod($method);
    }

    /** @return iterable<string, array{string}> */
    public static function compatibleMethods(): iterable
    {
        yield 'typed trailing variadic receives no arguments' => ['typedTrailingVariadic'];

        yield 'variadic captures handler' => ['handlerVariadic'];

        yield 'variadic captures request and handler' => ['allVariadic'];

        yield 'optional third argument' => ['optional'];

        yield 'union parameters and response' => ['union'];

        yield 'intersection parameter and response' => ['intersection'];
    }

    #[DataProvider('incompatibleMethods')]
    public function testRejectsIncompatibleSignature(string $method): void
    {
        $this->expectException(InvalidRouteDefinitionException::class);

        (new MethodSignatureValidator())->validate(
            new ReflectionMethod(CallableActionTrailingVariadic::class, $method),
            CallableActionTrailingVariadic::class
        );
    }

    /** @return iterable<string, array{string}> */
    public static function incompatibleMethods(): iterable
    {
        yield 'required third argument before variadic' => ['requiredThird'];

        yield 'required third argument without variadic' => ['requiredThirdWithoutVariadic'];

        yield 'incompatible variadic capturing handler' => ['incompatibleHandlerVariadic'];

        yield 'request-only variadic cannot capture handler' => ['incompatibleAllVariadic'];
    }

    private function assertInvokesMethod(string $method): void
    {
        $service  = new CallableActionTrailingVariadic();
        $request  = $this->createStub(ServerRequestInterface::class);
        $response = $this->createStub(ResponseInterface::class);
        $handler  = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        self::assertSame($response, (new MethodInvokerMiddleware($service, $method))->process($request, $handler));
        self::assertSame('optional' === $method ? ['default'] : [], $service->rest);
    }
}
