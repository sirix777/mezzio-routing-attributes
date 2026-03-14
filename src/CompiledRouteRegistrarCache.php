<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes;

use Mezzio\Router\RouteCollectorInterface;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;
use Throwable;

use function array_chunk;
use function array_key_exists;
use function count;
use function dirname;
use function file_put_contents;
use function implode;
use function is_array;
use function is_callable;
use function is_dir;
use function is_file;
use function mkdir;
use function rename;
use function restore_error_handler;
use function set_error_handler;
use function trim;
use function uniqid;
use function unlink;
use function var_export;

final readonly class CompiledRouteRegistrarCache
{
    public const CACHE_FORMAT_VERSION = 1;
    private const INLINE_ROUTE_LIMIT = 256;
    private const CHUNK_SIZE = 1000;
    private const ROUTE_OPTION_MIDDLEWARE_DISPLAY = 'sirix_routing_attributes.middleware_display';

    public function __construct(private ?string $cacheFile = null) {}

    public function registerRoutes(RouteCollectorInterface $collector, MiddlewarePipelineFactory $pipelineFactory): bool
    {
        if (null === $this->cacheFile || ! is_file($this->cacheFile)) {
            return false;
        }

        $artifact = $this->loadArtifact();
        if (null === $artifact) {
            return false;
        }

        try {
            $registrar = $artifact['register'];
            $registrar($collector, $pipelineFactory);
        } catch (Throwable $error) {
            $this->invalidPayload('Failed to register compiled routes: ' . $error->getMessage());
        }

        return true;
    }

    public function hasUsableArtifact(): bool
    {
        if (null === $this->cacheFile || ! is_file($this->cacheFile)) {
            return false;
        }

        try {
            return null !== $this->loadArtifact();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param list<RouteDefinition> $routes
     */
    public function save(array $routes): void
    {
        if (null === $this->cacheFile) {
            return;
        }

        $directory = dirname($this->cacheFile);
        if (! is_dir($directory)) {
            $mkdirError = null;
            $mkdirResult = $this->mkdirWithCapturedError($directory, $mkdirError);
            if (! $mkdirResult && ! is_dir($directory)) {
                return;
            }
        }

        $tmpFile = $this->cacheFile . '.tmp.' . uniqid('', true);
        $content = $this->buildCompiledArtifact($routes);
        $writeError = null;
        if (false === $this->filePutContentsWithCapturedError($tmpFile, $content, $writeError)) {
            return;
        }

        $renameError = null;
        if (! $this->renameWithCapturedError($tmpFile, $this->cacheFile, $renameError)) {
            $unlinkError = null;
            $this->unlinkWithCapturedError($tmpFile, $unlinkError);
        }
    }

    /**
     * @return null|array{register: callable(RouteCollectorInterface, MiddlewarePipelineFactory): void}
     */
    private function loadArtifact(): ?array
    {
        /**
         * @var array<string, array{
         *     payload: array{
         *         register: callable(RouteCollectorInterface, MiddlewarePipelineFactory): void
         *     }
         * }>
         *
         * In performance-first mode payload is immutable for process lifetime.
         * If cache file is replaced, restart/reload worker processes to apply changes.
         */
        static $loadedArtifacts = [];

        if (null === $this->cacheFile) {
            return null;
        }

        if (isset($loadedArtifacts[$this->cacheFile])) {
            return $loadedArtifacts[$this->cacheFile]['payload'];
        }

        $requireError = null;

        try {
            $payload = $this->requireWithCapturedError($this->cacheFile, $requireError);
        } catch (Throwable $error) {
            $this->invalidPayload('Failed to load compiled cache payload: ' . $error->getMessage());
        }

        if (! is_array($payload)) {
            $this->invalidPayload('Top-level value must be an array.' . $this->formatReason($requireError));
        }

        if (! isset($payload['register']) || ! is_callable($payload['register'])) {
            $this->invalidPayload('Compiled cache payload must contain callable key "register".');
        }

        $artifact = [
            'register' => $payload['register'],
        ];

        $loadedArtifacts[$this->cacheFile] = [
            'payload' => $artifact,
        ];

        return $artifact;
    }

    /**
     * @param list<RouteDefinition> $routes
     */
    private function buildCompiledArtifact(array $routes): string
    {
        $routesCode = count($routes) <= self::INLINE_ROUTE_LIMIT
            ? $this->buildInlineRoutesCode($routes)
            : $this->buildChunkedRoutesCode($routes);

        return <<<PHP
            <?php

            declare(strict_types=1);

            use Mezzio\\Router\\RouteCollectorInterface;
            use Sirix\\Mezzio\\Routing\\Attributes\\MiddlewarePipelineFactory;

            return [
                'register' => static function(RouteCollectorInterface \$collector, MiddlewarePipelineFactory \$pipelineFactory): void {
            {$routesCode}    },
            ];
            PHP;
    }

    /**
     * @param list<RouteDefinition> $routes
     */
    private function buildInlineRoutesCode(array $routes): string
    {
        $signatureTable = [];
        $signatureIndexByKey = [];
        $routeLines = [];
        $routeOptionKey = var_export(self::ROUTE_OPTION_MIDDLEWARE_DISPLAY, true);

        foreach ($routes as $route) {
            $signatureKey = $this->routeSignatureKey(
                $route->handlerService,
                $route->handlerMethod,
                $route->middlewareServices
            );
            if (! array_key_exists($signatureKey, $signatureIndexByKey)) {
                $signatureIndexByKey[$signatureKey] = count($signatureTable);
                $signatureTable[] = [
                    'handlerService' => $route->handlerService,
                    'handlerMethod' => $route->handlerMethod,
                    'middlewareServices' => $route->middlewareServices,
                ];
            }

            $path = var_export($route->path, true);
            $methods = var_export($route->methods, true);
            $name = var_export($this->normalizeRouteName($route->name), true);
            $middlewareDisplay = var_export(
                $this->buildMiddlewareDisplay($route->handlerService, $route->handlerMethod, $route->middlewareServices),
                true
            );
            $middlewareVariable = '$compiledMiddlewares[' . $signatureIndexByKey[$signatureKey] . ']';

            $routeLines[] = <<<PHP
                    \$route = \$collector->route({$path}, {$middlewareVariable}, {$methods}, {$name});
                    \$options = \$route->getOptions();
                    \$options[{$routeOptionKey}] = {$middlewareDisplay};
                    \$route->setOptions(\$options);
                PHP;
        }

        if ([] === $routeLines) {
            return '';
        }

        $signatureLines = [];
        foreach ($signatureTable as $signatureIndex => $signatureRow) {
            $handlerService = var_export($signatureRow['handlerService'], true);
            $handlerMethod = var_export($signatureRow['handlerMethod'], true);
            $middlewareServices = var_export($signatureRow['middlewareServices'], true);
            $signatureLines[] = <<<PHP
                    \$compiledMiddlewares[{$signatureIndex}] = \$pipelineFactory->createFromCompiled(
                        {$handlerService},
                        {$handlerMethod},
                        {$middlewareServices}
                    );
                PHP;
        }

        return '        $compiledMiddlewares = [];' . "\n"
            . '        ' . implode("\n\n        ", $signatureLines) . "\n\n"
            . '        ' . implode("\n\n        ", $routeLines) . "\n";
    }

    /**
     * @param list<RouteDefinition> $routes
     */
    private function buildChunkedRoutesCode(array $routes): string
    {
        $serviceTable = [];
        $serviceIndexByValue = [];
        $methodTable = [];
        $methodIndexByValue = [];
        $middlewareTable = [];
        $middlewareIndexBySignature = [];
        $compiledSignatureTable = [];
        $compiledSignatureIndexByValue = [];
        $routeRows = [];

        foreach ($routes as $route) {
            $handlerServiceIndex = $this->addStringToTable($serviceTable, $serviceIndexByValue, $route->handlerService);
            $handlerMethodIndex = $this->addStringToTable($methodTable, $methodIndexByValue, $route->handlerMethod);

            $middlewareServiceIndexes = [];
            foreach ($route->middlewareServices as $middlewareService) {
                $middlewareServiceIndexes[] = $this->addStringToTable($serviceTable, $serviceIndexByValue, $middlewareService);
            }

            $middlewareSignature = implode('|', $middlewareServiceIndexes);
            if (! array_key_exists($middlewareSignature, $middlewareIndexBySignature)) {
                $middlewareIndexBySignature[$middlewareSignature] = count($middlewareTable);
                $middlewareTable[] = $middlewareServiceIndexes;
            }

            $compiledSignature = $handlerServiceIndex . ':' . $handlerMethodIndex . ':' . $middlewareIndexBySignature[$middlewareSignature];
            if (! array_key_exists($compiledSignature, $compiledSignatureIndexByValue)) {
                $compiledSignatureIndexByValue[$compiledSignature] = count($compiledSignatureTable);
                $compiledSignatureTable[] = [
                    $handlerServiceIndex,
                    $handlerMethodIndex,
                    $middlewareIndexBySignature[$middlewareSignature],
                ];
            }

            $routeRows[] = [
                $route->path,
                $route->methods,
                $compiledSignatureIndexByValue[$compiledSignature],
                $this->normalizeRouteName($route->name),
                $this->buildMiddlewareDisplay($route->handlerService, $route->handlerMethod, $route->middlewareServices),
            ];
        }

        $routeChunks = array_chunk($routeRows, self::CHUNK_SIZE);
        $routeOptionKey = var_export(self::ROUTE_OPTION_MIDDLEWARE_DISPLAY, true);
        $serviceTableExport = var_export($serviceTable, true);
        $methodTableExport = var_export($methodTable, true);
        $middlewareTableExport = var_export($middlewareTable, true);
        $compiledSignatureTableExport = var_export($compiledSignatureTable, true);
        $routeChunksExport = var_export($routeChunks, true);

        return <<<PHP
                \$serviceTable = {$serviceTableExport};
                \$methodTable = {$methodTableExport};
                \$middlewareTable = {$middlewareTableExport};
                \$compiledSignatureTable = {$compiledSignatureTableExport};
                \$routeChunks = {$routeChunksExport};
                \$compiledMiddlewares = [];

                foreach (\$compiledSignatureTable as \$signatureIndex => \$signatureRow) {
                    \$handlerService = \$serviceTable[\$signatureRow[0]];
                    \$handlerMethod = \$methodTable[\$signatureRow[1]];
                    \$middlewareServices = [];
                    foreach (\$middlewareTable[\$signatureRow[2]] as \$middlewareServiceIndex) {
                        \$middlewareServices[] = \$serviceTable[\$middlewareServiceIndex];
                    }

                    \$compiledMiddlewares[\$signatureIndex] = \$pipelineFactory->createFromCompiled(
                        \$handlerService,
                        \$handlerMethod,
                        \$middlewareServices
                    );
                }

                foreach (\$routeChunks as \$chunk) {
                    foreach (\$chunk as \$row) {
                        \$path = \$row[0];
                        \$methods = \$row[1];
                        \$route = \$collector->route(\$path, \$compiledMiddlewares[\$row[2]], \$methods, \$row[3]);
                        \$options = \$route->getOptions();
                        \$options[{$routeOptionKey}] = \$row[4];
                        \$route->setOptions(\$options);
                    }
                }
            PHP;
    }

    /**
     * @param list<non-empty-string> $middlewareServices
     */
    private function routeSignatureKey(string $handlerService, string $handlerMethod, array $middlewareServices): string
    {
        return $handlerService . "\0" . $handlerMethod . "\0" . implode("\0", $middlewareServices);
    }

    /**
     * @param list<string>       $table
     * @param array<string, int> $indexByValue
     */
    private function addStringToTable(array &$table, array &$indexByValue, string $value): int
    {
        if (array_key_exists($value, $indexByValue)) {
            return $indexByValue[$value];
        }

        $index = count($table);
        $table[] = $value;
        $indexByValue[$value] = $index;

        return $index;
    }

    /**
     * @return null|non-empty-string
     */
    private function normalizeRouteName(?string $name): ?string
    {
        if (null === $name) {
            return null;
        }

        $name = trim($name);
        if ('' === $name) {
            return null;
        }

        return $name;
    }

    /**
     * @param list<non-empty-string> $middlewareServices
     */
    private function buildMiddlewareDisplay(string $handlerService, string $handlerMethod, array $middlewareServices): string
    {
        if ([] === $middlewareServices) {
            return $handlerService . '::' . $handlerMethod;
        }

        return implode(' -> ', [...$middlewareServices, $handlerService . '::' . $handlerMethod]);
    }

    private function invalidPayload(string $reason): never
    {
        throw InvalidConfigurationException::invalidCachePayload($reason);
    }

    private function formatReason(?string $reason): string
    {
        if (null === $reason || '' === $reason) {
            return '';
        }

        return ': ' . $reason;
    }

    private function mkdirWithCapturedError(string $directory, ?string &$error): bool
    {
        $error = null;
        set_error_handler(static function(int $severity, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });

        try {
            return mkdir($directory, 0o775, true);
        } finally {
            restore_error_handler();
        }
    }

    private function filePutContentsWithCapturedError(string $file, string $content, ?string &$error): bool|int
    {
        $error = null;
        set_error_handler(static function(int $severity, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });

        try {
            return file_put_contents($file, $content);
        } finally {
            restore_error_handler();
        }
    }

    private function renameWithCapturedError(string $source, string $target, ?string &$error): bool
    {
        $error = null;
        set_error_handler(static function(int $severity, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });

        try {
            return rename($source, $target);
        } finally {
            restore_error_handler();
        }
    }

    private function requireWithCapturedError(string $file, ?string &$error): mixed
    {
        $error = null;
        set_error_handler(static function(int $severity, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });

        try {
            return require $file;
        } finally {
            restore_error_handler();
        }
    }

    private function unlinkWithCapturedError(string $file, ?string &$error): bool
    {
        $error = null;
        set_error_handler(static function(int $severity, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });

        try {
            return unlink($file);
        } finally {
            restore_error_handler();
        }
    }
}
