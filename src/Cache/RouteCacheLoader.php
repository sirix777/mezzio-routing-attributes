<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Cache;

use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;
use Throwable;

use function is_array;
use function is_callable;
use function is_file;
use function restore_error_handler;
use function set_error_handler;

final readonly class RouteCacheLoader
{
    /**
     * @return null|array{register: callable}
     */
    public function load(string $cacheFile): ?array
    {
        static $loadedArtifacts = [];

        if (isset($loadedArtifacts[$cacheFile])) {
            return $loadedArtifacts[$cacheFile]['payload'];
        }

        if (! is_file($cacheFile)) {
            return null;
        }

        $requireError = null;

        try {
            $payload = $this->requireWithCapturedError($cacheFile, $requireError);
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

        $loadedArtifacts[$cacheFile] = [
            'payload' => $artifact,
        ];

        return $artifact;
    }

    public function validate(mixed $payload): bool
    {
        return is_array($payload)
            && isset($payload['register'])
            && is_callable($payload['register']);
    }

    private function withCapturedError(callable $callback, ?string &$error): mixed
    {
        $error = null;
        set_error_handler(static function(int $severity, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }

    private function requireWithCapturedError(string $file, ?string &$error): mixed
    {
        return $this->withCapturedError(fn () => require $file, $error);
    }

    private function formatReason(?string $reason): string
    {
        if (null === $reason || '' === $reason) {
            return '';
        }

        return ': ' . $reason;
    }

    private function invalidPayload(string $reason): never
    {
        throw InvalidConfigurationException::invalidCachePayload($reason);
    }
}
