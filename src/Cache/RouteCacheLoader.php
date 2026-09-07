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
            $payload = CapturedError::run(fn () => require $cacheFile, $requireError);
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

    private function isSafeArtifact(string $cacheFile): bool
    {
        if (is_link($cacheFile)) {
            return false;
        }

        $lstatError = null;
        $stat       = CapturedError::run(fn () => lstat($cacheFile), $lstatError);
        if (false === $stat) {
            return false;
        }

        return ($stat['mode'] & 0o170000) === 0o100000;
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
