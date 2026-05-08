<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Cache;

use Sirix\Mezzio\Routing\Attributes\RouteDefinition;

use function array_chunk;
use function array_key_exists;
use function count;
use function implode;
use function trim;
use function var_export;

final readonly class RouteCacheGenerator
{
    private const INLINE_ROUTE_LIMIT = 256;
    private const CHUNK_SIZE = 1000;
    private const ROUTE_OPTION_MIDDLEWARE_DISPLAY = 'sirix_routing_attributes.middleware_display';

    /**
     * @param list<RouteDefinition> $routes
     */
    public function generate(array $routes): string
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
            $defaultsCode = $this->buildDefaultsCode($route->defaults);

            $routeLines[] = <<<PHP
                    \$route = \$collector->route({$path}, {$middlewareVariable}, {$methods}, {$name});
                    \$options = \$route->getOptions();
                    \$options[{$routeOptionKey}] = {$middlewareDisplay};
                    {$defaultsCode}\$route->setOptions(\$options);
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
                $route->defaults,
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
                        if ([] !== \$row[5]) {
                            \$options = [...\$options, ...\$row[5]];
                        }
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

    /**
     * @param array<string, mixed> $defaults
     */
    private function buildDefaultsCode(array $defaults): string
    {
        if ([] === $defaults) {
            return '';
        }

        $exported = var_export($defaults, true);

        return "\$options = [...\$options, ...{$exported}];\n                    ";
    }
}
