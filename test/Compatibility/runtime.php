<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Compatibility;

use Laminas\Cli\ApplicationFactory;
use Mezzio\Tooling\Routes\ConfigLoaderInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheGenerator;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheLoader;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheStorage;
use Sirix\Mezzio\Routing\Attributes\Command\ListRoutesCommand;
use Sirix\Mezzio\Routing\Attributes\CompiledRouteRegistrarCache;
use Sirix\Mezzio\Routing\Attributes\ConfigProvider;
use Sirix\Mezzio\Routing\Attributes\MiddlewarePipelineFactory;
use Sirix\Mezzio\Routing\Attributes\RouteDefinition;
use Sirix\Mezzio\Routing\Attributes\RouteRegistrar;
use Sirix\Mezzio\Routing\Attributes\ServiceMiddlewareResolver;
use SirixTest\Mezzio\Routing\Attributes\TestAsset\RecordingRouteCollector;
use Symfony\Component\Console\Command\Command;

use function bin2hex;
use function class_exists;
use function dirname;
use function interface_exists;
use function is_file;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

require $argv[1] ?? throw new RuntimeException('Pass the isolated vendor/autoload.php');

require_once dirname(__DIR__) . '/TestAsset/RecordingRouteCollector.php';

function verify(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

verify(! class_exists(Command::class), 'Console unexpectedly installed');
verify(! class_exists(ApplicationFactory::class), 'Laminas CLI unexpectedly installed');
verify(! interface_exists(ConfigLoaderInterface::class), 'Tooling unexpectedly installed');
$config = (new ConfigProvider())();
verify(! isset($config['laminas-cli']), 'CLI registered without optional packages');
verify(! isset($config['dependencies']['factories'][ListRoutesCommand::class]), 'Console factory registered without packages');

// Untyped IDs are required by psr/container 1.0, the declared runtime floor.
$container = new class implements ContainerInterface {
    public function get($id): mixed
    {
        throw new RuntimeException('Registration must stay lazy: ' . $id);
    }

    public function has($id): bool
    {
        return false;
    }
};
$pipeline = new MiddlewarePipelineFactory($container, new ServiceMiddlewareResolver());
$routes   = [
    new RouteDefinition('/minimum', ['GET'], 'handler', 'handle', [], 'minimum', [
        'locale' => 'en',
    ])];
$cold = new RecordingRouteCollector();
(new RouteRegistrar())->register($cold, $routes, $pipeline);
verify('minimum' === $cold->lastRoute?->getName(), 'Cold registration failed');
verify('en' === $cold->lastRoute?->getOptions()['locale'], 'Route defaults lost');

$directory = sys_get_temp_dir() . '/routing-minimum-' . bin2hex(random_bytes(8));
if (! mkdir($directory, 0o700)) {
    throw new RuntimeException('Cannot create temporary directory');
}
$file = $directory . '/routes.php';

try {
    $cache = new CompiledRouteRegistrarCache($file, new RouteCacheGenerator(), new RouteCacheStorage(), new RouteCacheLoader());
    verify($cache->save($routes), 'Cache save failed');
    $warm = new RecordingRouteCollector();
    verify($cache->registerRoutes($warm, $pipeline), 'Compiled registration failed');
    verify($warm->lastRoute?->getName() === $cold->lastRoute?->getName(), 'Compiled route differs');
    verify($warm->lastRoute?->getOptions() === $cold->lastRoute?->getOptions(), 'Compiled options differ');
    echo "Minimum runtime registration/cache and absent-console checks passed.\n";
} finally {
    if (is_file($file)) {
        verify(unlink($file), 'Cannot remove cache fixture');
    }
    verify(rmdir($directory), 'Cannot remove temporary directory');
}
