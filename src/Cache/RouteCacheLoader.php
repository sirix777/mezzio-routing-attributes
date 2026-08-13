<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Cache;

use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;
use Throwable;

use function hash_equals;
use function is_array;
use function is_callable;
use function is_link;
use function is_string;
use function lstat;
use function restore_error_handler;
use function set_error_handler;

final class RouteCacheLoader
{
    /** @var array<string, array{payload: array{register: callable}}> */
    private array $loadedArtifacts = [];

    /**
     * @return null|array{register: callable}
     */
    public function load(string $cacheFile, string $expectedFingerprint = ''): ?array
    {
        $cacheKey = $cacheFile . "\0" . $expectedFingerprint;
        if (isset($this->loadedArtifacts[$cacheKey])) {
            return $this->loadedArtifacts[$cacheKey]['payload'];
        }

        if (! $this->isSafeArtifact($cacheFile)) {
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

        if (($payload['format_version'] ?? null) !== RouteCacheGenerator::FORMAT_VERSION) {
            return null;
        }

        if (
            ! isset($payload['config_fingerprint'])
            || ! is_string($payload['config_fingerprint'])
            || ! hash_equals($expectedFingerprint, $payload['config_fingerprint'])
        ) {
            return null;
        }

        if (! isset($payload['register']) || ! is_callable($payload['register'])) {
            $this->invalidPayload('Compiled cache payload must contain callable key "register".');
        }

        $artifact = [
            'register' => $payload['register'],
        ];

        $this->loadedArtifacts[$cacheKey] = [
            'payload' => $artifact,
        ];

        return $artifact;
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

    private function isSafeArtifact(string $cacheFile): bool
    {
        if (is_link($cacheFile)) {
            return false;
        }

        $lstatError = null;
        $stat       = $this->lstatWithCapturedError($cacheFile, $lstatError);
        if (false === $stat) {
            return false;
        }

        return (($stat['mode'] ?? 0) & 0o170000) === 0o100000;
    }

    /** @return array<string, int>|false */
    private function lstatWithCapturedError(string $file, ?string &$error): array|false
    {
        return $this->withCapturedError(fn () => lstat($file), $error);
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
