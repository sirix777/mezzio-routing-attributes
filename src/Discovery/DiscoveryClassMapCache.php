<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Discovery;

use Sirix\Mezzio\Routing\Attributes\Exception\InvalidConfigurationException;

use function array_key_exists;
use function dirname;
use function file_put_contents;
use function hash_final;
use function hash_init;
use function hash_update;
use function is_array;
use function is_dir;
use function is_file;
use function is_int;
use function is_string;
use function mkdir;
use function restore_error_handler;
use function set_error_handler;
use function usort;
use function var_export;

final readonly class DiscoveryClassMapCache
{
    public const CACHE_FORMAT_VERSION = 3;
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
            ! array_key_exists('format_version', $payload)
            || ! array_key_exists('paths', $payload)
            || ! array_key_exists('classes', $payload)
            || ! array_key_exists('fingerprint', $payload)
            || ! array_key_exists('inventory_fingerprint', $payload)
            || ! array_key_exists('options_signature', $payload)
        ) {
            return null;
        }

        if (! is_int($payload['format_version']) || self::CACHE_FORMAT_VERSION !== $payload['format_version']) {
            return null;
        }

        if (! is_array($payload['paths']) || $payload['paths'] !== $this->paths) {
            return null;
        }

        if (
            ! is_array($payload['classes'])
            || ! is_string($payload['fingerprint'])
            || '' === $payload['fingerprint']
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

        if ($this->validateCache) {
            $currentFiles = $this->fileInventory()->collect();
            if ($this->createInventoryFingerprint($currentFiles) !== $payload['inventory_fingerprint']) {
                return null;
            }
        }

        if (! $this->isValidClassList($payload['classes'])) {
            return null;
        }

        /** @var list<non-empty-string> $classes */
        $classes = $payload['classes'];

        return [
            'classes' => $classes,
            'fingerprint' => $payload['fingerprint'],
        ];
    }

    /**
     * @param list<non-empty-string>                   $classes
     * @param list<array{0: non-empty-string, 1: int}> $files
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
            'format_version' => self::CACHE_FORMAT_VERSION,
            'paths' => $this->paths,
            'classes' => $classes,
            'fingerprint' => $fingerprint,
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
     * @param list<array{0: non-empty-string, 1: int}> $files
     *
     * @return non-empty-string
     */
    public function createInventoryFingerprint(array $files): string
    {
        $hashContext = hash_init('sha256');
        foreach ($this->sortFilesByPath($files) as [$file, $mtime]) {
            hash_update($hashContext, $file);
            hash_update($hashContext, '@');
            hash_update($hashContext, (string) $mtime);
            hash_update($hashContext, '|');
        }

        return hash_final($hashContext);
    }

    /**
     * @param list<array{0: non-empty-string, 1: int}> $files
     *
     * @return list<array{0: non-empty-string, 1: int}>
     */
    private function sortFilesByPath(array $files): array
    {
        usort($files, static fn (array $left, array $right): int => $left[0] <=> $right[0]);

        return $files;
    }

    /**
     * @param array<int|string, mixed> $classes
     */
    private function isValidClassList(array $classes): bool
    {
        $expectedIndex = 0;
        foreach ($classes as $index => $className) {
            if (! is_int($index) || $index !== $expectedIndex) {
                return false;
            }
            ++$expectedIndex;

            if (! is_string($className) || '' === $className) {
                return false;
            }
        }

        return true;
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
