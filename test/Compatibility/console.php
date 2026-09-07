<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Compatibility;

use Laminas\Diactoros\Response\TextResponse;
use Laminas\ServiceManager\ServiceManager;
use Mezzio\Router\RouteCollector;
use Mezzio\Tooling\Routes\ConfigLoaderInterface;
use Mezzio\Tooling\Routes\ListRoutesCommand as UpstreamCommand;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Routing\Attributes\Command\ListRoutesCommand;
use Sirix\Mezzio\Routing\Attributes\ConfigProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function array_column;
use function array_merge_recursive;
use function bin2hex;
use function chdir;
use function count;
use function file_put_contents;
use function getcwd;
use function in_array;
use function interface_exists;
use function is_dir;
use function is_file;
use function is_string;
use function json_decode;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sort;
use function str_contains;
use function sys_get_temp_dir;
use function unlink;

require $argv[1] ?? throw new RuntimeException('Pass the isolated vendor/autoload.php');
$withTooling = ($argv[2] ?? '') === 'tooling';
check(interface_exists(ConfigLoaderInterface::class) === $withTooling, 'Unexpected Tooling installation');

#[Get('/attribute', name: 'attribute.get')]
#[Post('/attribute', name: 'attribute.post')]
final class ConsoleHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new TextResponse('attribute');
    }
}

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

// A fresh container per invocation reflects independent CLI processes and prevents
// repeated config loading from accidentally registering the same classic route twice.
function container(bool $withTooling, bool $override): ServiceManager
{
    $providers = [
        new \Mezzio\ConfigProvider(),
        new \Mezzio\Router\ConfigProvider(),
        new \Sirix\Mezzio\Router\RadixRouter\ConfigProvider(),
    ];
    if ($withTooling) {
        $providers[] = new \Mezzio\Tooling\ConfigProvider();
    }
    $providers[] = new ConfigProvider();
    $config      = [];
    foreach ($providers as $provider) {
        $config = array_merge_recursive($config, $provider());
    }
    $config['routing_attributes'] = [
        'classes'                             => [ConsoleHandler::class],
        'override_mezzio_routes_list_command' => $override,
    ];
    $config['dependencies']['services']['config']                = $config;
    $config['dependencies']['invokables'][ConsoleHandler::class] = ConsoleHandler::class;

    return new ServiceManager($config['dependencies']);
}

$previousDirectory = getcwd();
if (! is_string($previousDirectory)) {
    throw new RuntimeException('Cannot determine working directory');
}
$workingDirectory = sys_get_temp_dir() . '/routing-console-' . bin2hex(random_bytes(8));
if (! mkdir($workingDirectory, 0o700)) {
    throw new RuntimeException('Cannot create temporary directory');
}

try {
    check(mkdir($workingDirectory . '/config', 0o700), 'Cannot create config directory');
    check(false !== file_put_contents($workingDirectory . '/config/routes.php', <<<'PHP'
        <?php
        return static function (
            \Mezzio\Application $app,
            \Mezzio\MiddlewareFactory $factory,
            \Psr\Container\ContainerInterface $container
        ): void {
            $app->get('/classic', \SirixTest\Mezzio\Routing\Attributes\Compatibility\ConsoleHandler::class, 'classic.get');
        };
        PHP), 'Cannot write routes fixture');
    check(chdir($workingDirectory), 'Cannot enter fixture directory');
    $cases = [[false, ListRoutesCommand::class]];
    if ($withTooling) {
        $cases[] = [false, UpstreamCommand::class];
        $cases[] = [true, UpstreamCommand::class];
    }

    foreach ($cases as [$override, $service]) {
        foreach (['json', 'table', 'filtered', 'empty'] as $format) {
            $container = container($withTooling, $override);
            $config    = $container->get('config');
            check(ListRoutesCommand::class === $config['laminas-cli']['commands']['routing-attributes:routes:list'], 'Package command missing');
            check($config['laminas-cli']['commands']['mezzio:routes:list'] === ($withTooling ? UpstreamCommand::class : ListRoutesCommand::class), 'Unexpected upstream alias');
            $command = $container->get($service);
            check($command instanceof Command, 'Command factory failed');
            check($command instanceof ListRoutesCommand === ($override || ListRoutesCommand::class === $service), 'Override policy failed');
            if ($withTooling) {
                $loader = $container->get(ConfigLoaderInterface::class);
                $source = (new ReflectionClass($loader))->getFileName();
                check(is_string($source) && str_contains($source, 'vendor/mezzio/mezzio-tooling/'), 'Real Tooling config loader was not used');
            }
            $tester  = new CommandTester($command);
            $options = [
                '--format' => 'table' === $format ? 'table' : 'json',
            ];
            if ('filtered' === $format || 'empty' === $format) {
                $options += [
                    '--has-name'        => 'attribute',
                    '--has-path'        => 'empty' === $format ? '/missing' : '/attribute',
                    '--supports-method' => 'empty' === $format ? 'DELETE' : 'POST',
                ];
            }
            check(0 === $tester->execute($options), 'Console command failed');
            $display = $tester->getDisplay();
            if ('table' === $format) {
                foreach (['Name', 'Path', 'Methods', 'Middleware', 'classic.get', 'attribute.get', 'attribute.post'] as $text) {
                    check(str_contains($display, $text), 'Missing table value: ' . $text);
                }
            } else {
                $rows  = json_decode($display, true, 512, JSON_THROW_ON_ERROR);
                $names = array_column($rows, 'name');
                sort($names);
                // Tooling 2.13 prioritizes --has-name over other filters;
                // the package and its enabled override deliberately use AND semantics.
                $expected = match ($format) {
                    'filtered' => ['attribute.post'],
                    'empty'    => [],
                    default    => ['attribute.get', 'attribute.post', 'classic.get'],
                };
                if ($command instanceof UpstreamCommand && in_array($format, ['filtered', 'empty'], true)) {
                    $expected = ['attribute.get', 'attribute.post'];
                }
                check($names === $expected, 'Wrong JSON routes: ' . $display);
            }
            check(3 === count($container->get(RouteCollector::class)->getRoutes()), 'Classic and attribute registration not integrated');
        }
    }
    echo $withTooling ? "Real Tooling integration passed (12 scenarios).\n" : "No-Tooling fallback passed (4 scenarios).\n";
} finally {
    check(chdir($previousDirectory), 'Cannot restore working directory');
    if (is_file($workingDirectory . '/config/routes.php')) {
        check(unlink($workingDirectory . '/config/routes.php'), 'Cannot remove routes fixture');
    }

    if (is_dir($workingDirectory . '/config')) {
        check(rmdir($workingDirectory . '/config'), 'Cannot remove config directory');
    }
    check(rmdir($workingDirectory), 'Cannot remove temporary directory');
}
