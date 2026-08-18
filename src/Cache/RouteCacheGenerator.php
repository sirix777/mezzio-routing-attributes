<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Cache;

use ReflectionReference;
use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;
use Sirix\Mezzio\Routing\Attributes\MiddlewareSignatureKey;
use Sirix\Mezzio\Routing\Attributes\RouteDefinition;
use Sirix\Mezzio\Routing\Attributes\RouteMiddlewareDisplay;
use Sirix\Mezzio\Routing\Attributes\RouteRegistrar;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;

use function array_key_exists;
use function count;
use function get_debug_type;
use function implode;
use function is_array;
use function is_scalar;
use function var_export;

final readonly class RouteCacheGenerator
{
    public const FORMAT_VERSION = 2;

    /**
     * @param list<RouteDefinition> $routes
     */
    public function generate(array $routes, string $configFingerprint = ''): string
    {
        $this->assertCacheCompatibleDefaults($routes);

        $routesCode = $this->buildPreparedRoutesCode($routes);

        return <<<PHP
            <?php

            declare(strict_types=1);

            use Mezzio\\Router\\RouteCollectorInterface;
            use Sirix\\Mezzio\\Routing\\Attributes\\MiddlewarePipelineFactory;
            use Sirix\\Mezzio\\Routing\\Attributes\\RouteRegistrar;

            return [
                'format_version' => {$this->formatVersionCode()},
                'config_fingerprint' => {$this->configFingerprintCode($configFingerprint)},
                'register' => static function(RouteCollectorInterface \$collector, MiddlewarePipelineFactory \$pipelineFactory): void {
            {$routesCode}    },
            ];
            PHP;
    }

    private function formatVersionCode(): string
    {
        return (string) self::FORMAT_VERSION;
    }

    private function configFingerprintCode(string $configFingerprint): string
    {
        return var_export($configFingerprint, true);
    }

    /**
     * @param list<RouteDefinition> $routes
     */
    private function buildPreparedRoutesCode(array $routes): string
    {
        $signatureTable      = [];
        $signatureIndexByKey = [];
        $routeRows           = [];

        foreach ($routes as $route) {
            $signatureKey = $this->routeSignatureKey(
                $route->handlerService,
                $route->handlerMethod,
                $route->middlewareServices
            );
            if (! array_key_exists($signatureKey, $signatureIndexByKey)) {
                $signatureIndexByKey[$signatureKey] = count($signatureTable);
                $signatureTable[]                   = [
                    'handlerService'     => $route->handlerService,
                    'handlerMethod'      => $route->handlerMethod,
                    'middlewareServices' => $route->middlewareServices,
                ];
            }

            $path               = var_export($route->path, true);
            $methods            = var_export($route->methods, true);
            $name               = var_export(RouteRegistrar::normalizeRouteName($route->name), true);
            $defaults           = var_export($route->defaults, true);

            $routeRows[] = "[{$path}, {$methods}, " . $signatureIndexByKey[$signatureKey] . ", {$name}, {$defaults}]";
        }

        if ([] === $routeRows) {
            return '';
        }

        $signatureLines     = [];
        $middlewareDisplays = [];
        foreach ($signatureTable as $signatureIndex => $signatureRow) {
            $handlerService       = var_export($signatureRow['handlerService'], true);
            $handlerMethod        = var_export($signatureRow['handlerMethod'], true);
            $middlewareServices   = var_export($signatureRow['middlewareServices'], true);
            $middlewareDisplays[] = var_export(
                RouteMiddlewareDisplay::format(
                    $signatureRow['handlerService'],
                    $signatureRow['handlerMethod'],
                    $signatureRow['middlewareServices']
                ),
                true
            );
            $signatureLines[]   = <<<PHP
                    \$compiledMiddlewares[{$signatureIndex}] = \$pipelineFactory->createUncachedFromSignature(
                        {$handlerService},
                        {$handlerMethod},
                        {$middlewareServices}
                    );
                PHP;
        }

        return '        $compiledMiddlewares = [];' . "\n"
            . '        ' . implode("\n\n        ", $signatureLines) . "\n\n"
            . '        $middlewareDisplays = [' . implode(', ', $middlewareDisplays) . "];\n\n"
            . '        $routeRows = [' . "\n"
            . '            ' . implode(",\n            ", $routeRows) . ",\n"
            . "        ];\n"
            . "        RouteRegistrar::registerPreparedRows(\$collector, \$compiledMiddlewares, \$routeRows, \$middlewareDisplays);\n";
    }

    /**
     * @param list<MiddlewareSpecification|non-empty-string> $middlewareServices
     */
    private function routeSignatureKey(string $handlerService, string $handlerMethod, array $middlewareServices): string
    {
        return MiddlewareSignatureKey::for($handlerService, $handlerMethod, $middlewareServices);
    }

    /**
     * @param list<RouteDefinition> $routes
     */
    private function assertCacheCompatibleDefaults(array $routes): void
    {
        foreach ($routes as $route) {
            foreach ($route->defaults as $key => $value) {
                $this->assertCacheCompatibleValue($value, $route->path, (string) $key, []);
            }
        }
    }

    /**
     * @param array<string, true> $activeArrayReferences
     */
    private function assertCacheCompatibleValue(mixed $value, string $path, string $key, array $activeArrayReferences): void
    {
        if (is_array($value)) {
            $this->assertCacheCompatibleArray($value, $path, $key, $activeArrayReferences);

            return;
        }

        if (null === $value || is_scalar($value)) {
            return;
        }

        throw InvalidConfigurationException::invalidCacheDefault($path, $key, get_debug_type($value));
    }

    /**
     * @param array<array-key, mixed> $value
     * @param array<string, true>     $activeArrayReferences
     */
    private function assertCacheCompatibleArray(array $value, string $path, string $key, array $activeArrayReferences): void
    {
        foreach ($value as $nestedKey => $nestedValue) {
            $nestedValueKey = $key . '[' . $nestedKey . ']';
            $reference      = ReflectionReference::fromArrayElement($value, $nestedKey);
            $referenceId    = null;
            if ($reference instanceof ReflectionReference && is_array($nestedValue)) {
                $referenceId = $reference->getId();
                if (isset($activeArrayReferences[$referenceId])) {
                    throw InvalidConfigurationException::recursiveCacheDefault($path, $nestedValueKey);
                }

                $activeArrayReferences[$referenceId] = true;
            }

            try {
                $this->assertCacheCompatibleValue(
                    $nestedValue,
                    $path,
                    $nestedValueKey,
                    $activeArrayReferences
                );
            } finally {
                if (null !== $referenceId) {
                    unset($activeArrayReferences[$referenceId]);
                }
            }
        }
    }
}
