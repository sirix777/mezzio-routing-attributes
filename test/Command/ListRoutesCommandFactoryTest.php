<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Command;

use Mezzio\Application;
use Mezzio\MiddlewareFactory;
use Mezzio\Router\Route;
use Mezzio\Router\RouteCollector;
use Mezzio\Router\RouteCollectorInterface;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Command\ListRoutesCommand;
use Sirix\Mezzio\Routing\Attributes\Command\ListRoutesCommandFactory;
use stdClass;
use Symfony\Component\Console\Tester\CommandTester;

use function chdir;
use function file_put_contents;
use function getcwd;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

final class ListRoutesCommandFactoryTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testFallsBackToConfigRoutesFileWhenToolingLoaderIsUnavailable(): void
    {
        $workingDirectory = sys_get_temp_dir() . '/routing-attributes-' . uniqid();
        mkdir($workingDirectory . '/config', 0o777, true);
        file_put_contents(
            $workingDirectory . '/config/routes.php',
            <<<'PHP'
                <?php

                return static function ($app): void {
                    $app->get('/classic', new class implements \Psr\Http\Server\MiddlewareInterface {
                        public function process(
                            \Psr\Http\Message\ServerRequestInterface $request,
                            \Psr\Http\Server\RequestHandlerInterface $handler
                        ): \Psr\Http\Message\ResponseInterface {
                            return $handler->handle($request);
                        }
                    }, 'classic.route');
                };
                PHP
        );

        $collector = new class implements RouteCollectorInterface {
            /** @var list<Route> */
            private array $routes = [];

            public function route(string $path, MiddlewareInterface $middleware, ?array $methods = null, ?string $name = null): Route
            {
                $route = new Route($path, $middleware, $methods, $name);
                $this->routes[] = $route;

                return $route;
            }

            public function get(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['GET'], $name);
            }

            public function post(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['POST'], $name);
            }

            public function put(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['PUT'], $name);
            }

            public function patch(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['PATCH'], $name);
            }

            public function delete(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['DELETE'], $name);
            }

            public function any(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, null, $name);
            }

            /** @return list<Route> */
            public function getRoutes(): array
            {
                return $this->routes;
            }
        };

        $application = new class($collector) {
            public function __construct(private readonly RouteCollectorInterface $collector) {}

            /**
             * @param non-empty-string      $path
             * @param null|non-empty-string $name
             */
            public function get(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->collector->get($path, $middleware, $name);
            }
        };

        $container = new class($collector, $application) implements ContainerInterface {
            public function __construct(private readonly object $collector, private readonly object $application) {}

            public function get(string $id): mixed
            {
                return match ($id) {
                    RouteCollector::class => $this->collector,
                    Application::class => $this->application,
                    MiddlewareFactory::class => new stdClass(),
                    'config' => [
                        'routing_attributes' => [
                            'classes' => [],
                        ],
                    ],
                    default => throw new RuntimeException('Unknown service: ' . $id),
                };
            }

            public function has(string $id): bool
            {
                return match ($id) {
                    RouteCollector::class,
                    Application::class,
                    MiddlewareFactory::class,
                    'config' => true,
                    default => false,
                };
            }
        };

        $previousDirectory = getcwd();
        self::assertIsString($previousDirectory);
        chdir($workingDirectory);

        try {
            $command = (new ListRoutesCommandFactory())($container);
            self::assertInstanceOf(ListRoutesCommand::class, $command);

            $tester = new CommandTester($command);
            self::assertSame(0, $tester->execute(['--format' => 'json']));
            self::assertStringContainsString('classic.route', $tester->getDisplay());
            self::assertStringContainsString('/classic', $tester->getDisplay());
        } finally {
            chdir($previousDirectory);
        }
    }
}
