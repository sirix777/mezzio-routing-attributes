<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Discovery;

use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;

use function array_key_exists;
use function count;
use function dirname;
use function file_put_contents;
use function filemtime;
use function hash;
use function implode;
use function is_array;
use function is_dir;
use function is_file;
use function is_int;
use function is_string;
use function ksort;
use function mkdir;
use function restore_error_handler;
use function set_error_handler;
use function var_export;

final readonly class DiscoveryClassMapCache
{
    public const WRITE_FAIL_STRATEGY_IGNORE = 'ignore';
    public const WRITE_FAIL_STRATEGY_THROW = 'throw';

    /**
     * @param list<non-empty-string> $paths
     */
    public function __construct(
        private array $paths,
        private ?string $cacheFile = null,
        private bool $validateCache = true,
        private ?DiscoveryFileInventory $fileInventory = null,
        private string $writeFailStrategy = self::WRITE_FAIL_STRATEGY_IGNORE,
        private string $optionsSignature = 'default'
    ) {}

    /**
     * @return null|array{
     *     classes: list<non-empty-string>,
     *     fingerprint: non-empty-string
     * }
     */
    public function load(): ?array
    {
        if (null === $this->cacheFile || ! is_file($this->cacheFile)) {
            return null;
        }

        $payload = require $this->cacheFile;
        if (! is_array($payload)) {
            return null;
        }

        if (
            ! array_key_exists('paths', $payload)
            || ! array_key_exists('files', $payload)
            || ! array_key_exists('classes', $payload)
            || ! array_key_exists('fingerprint', $payload)
            || ! array_key_exists('files_count', $payload)
            || ! array_key_exists('inventory_fingerprint', $payload)
            || ! array_key_exists('options_signature', $payload)
        ) {
            return null;
        }

        if (! is_array($payload['paths']) || $payload['paths'] !== $this->paths) {
            return null;
        }

        if (
            ! is_array($payload['files'])
            || ! is_array($payload['classes'])
            || ! is_string($payload['fingerprint'])
            || '' === $payload['fingerprint']
            || ! is_int($payload['files_count'])
            || $payload['files_count'] < 0
            || ! is_string($payload['inventory_fingerprint'])
            || '' === $payload['inventory_fingerprint']
            || ! is_string($payload['options_signature'])
            || '' === $payload['options_signature']
        ) {
            return null;
        }

        if ($payload['options_signature'] !== $this->optionsSignature) {
            return null;
        }

        $files = [];
        foreach ($payload['files'] as $file => $mtime) {
            if (! is_string($file) || '' === $file || ! is_int($mtime)) {
                return null;
            }

            $files[$file] = $mtime;
        }

        if ($this->validateCache) {
            $currentFiles = $this->fileInventory()->collect();
            if (count($currentFiles) !== $payload['files_count']) {
                return null;
            }

            if ($this->createInventoryFingerprint($currentFiles) !== $payload['inventory_fingerprint']) {
                return null;
            }

            foreach ($files as $file => $expectedMtime) {
                if (! is_file($file)) {
                    return null;
                }

                $actualMtime = filemtime($file);
                if (false === $actualMtime || $actualMtime !== $expectedMtime) {
                    return null;
                }
            }
        }

        $classes = [];
        foreach ($payload['classes'] as $className) {
            if (! is_string($className) || '' === $className) {
                return null;
            }

            $classes[] = $className;
        }

        return [
            'classes' => $classes,
            'fingerprint' => $payload['fingerprint'],
        ];
    }

    /**
     * @param list<non-empty-string>       $classes
     * @param array<non-empty-string, int> $files
     */
    public function save(array $classes, array $files, string $fingerprint): void
    {
        if (null === $this->cacheFile) {
            return;
        }

        $directory = dirname($this->cacheFile);
        if (! is_dir($directory)) {
            $mkdirError = null;
            $mkdirResult = $this->mkdirWithCapturedError($directory, $mkdirError);
            if (! $mkdirResult && ! is_dir($directory)) {
                $this->handleWriteFailure('create directory', $mkdirError);

                return;
            }
        }

        $payload = [
            'paths' => $this->paths,
            'files' => $files,
            'classes' => $classes,
            'fingerprint' => $fingerprint,
            'files_count' => count($files),
            'inventory_fingerprint' => $this->createInventoryFingerprint($files),
            'options_signature' => $this->optionsSignature,
        ];

        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($payload, true) . ";\n";
        $writeError = null;
        if (false === $this->filePutContentsWithCapturedError($this->cacheFile, $content, $writeError)) {
            $this->handleWriteFailure('write', $writeError);
        }
    }

    /**
     * @param array<non-empty-string, int> $files
     *
     * @return non-empty-string
     */
    public function createInventoryFingerprint(array $files): string
    {
        $chunks = [];
        $normalizedFiles = $files;
        ksort($normalizedFiles);
        foreach ($normalizedFiles as $file => $mtime) {
            $chunks[] = $file . '@' . $mtime;
        }

        return hash('sha256', implode('|', $chunks));
    }

    private function fileInventory(): DiscoveryFileInventory
    {
        return $this->fileInventory ?? new DiscoveryFileInventory($this->paths);
    }

    private function handleWriteFailure(string $operation, ?string $reason = null): void
    {
        if (self::WRITE_FAIL_STRATEGY_THROW === $this->writeFailStrategy && null !== $this->cacheFile) {
            throw InvalidConfigurationException::discoveryClassMapCacheWriteFailed($this->cacheFile, $operation, $reason);
        }
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
}
