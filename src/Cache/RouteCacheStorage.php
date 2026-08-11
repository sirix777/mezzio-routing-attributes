<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Cache;

use Psr\Log\LoggerInterface;
use Throwable;

use function dirname;
use function file_exists;
use function file_put_contents;
use function is_dir;
use function is_link;
use function lstat;
use function mkdir;
use function rename;
use function restore_error_handler;
use function set_error_handler;
use function strlen;
use function tempnam;
use function unlink;

final readonly class RouteCacheStorage
{
    public function __construct(private ?LoggerInterface $logger = null) {}

    public function save(string $cacheFile, string $content): bool
    {
        if (! $this->isSafeTarget($cacheFile)) {
            $this->reportFailure('validate_target', $cacheFile, 'Cache target is a symlink or is not a regular file.');

            return false;
        }

        $directory = dirname($cacheFile);
        if (! is_dir($directory)) {
            $mkdirError  = null;
            $mkdirResult = $this->mkdirWithCapturedError($directory, $mkdirError);
            if (! $mkdirResult && ! is_dir($directory)) {
                $this->reportFailure('create_directory', $cacheFile, $mkdirError);

                return false;
            }
        }

        $temporaryError  = null;
        $tmpFile         = $this->tempnamWithCapturedError($directory, $temporaryError);
        if (false === $tmpFile || ! $this->isSafeTarget($tmpFile)) {
            $this->reportFailure('create_temporary_file', $cacheFile, $temporaryError);

            return false;
        }

        $writeError = null;
        $written    = $this->filePutContentsWithCapturedError($tmpFile, $content, $writeError);
        if (false === $written || strlen($content) !== $written) {
            $this->reportFailure('write_temporary_file', $cacheFile, $writeError);
            $this->removeTemporaryFile($tmpFile, $cacheFile);

            return false;
        }

        $renameError = null;
        if (! $this->renameWithCapturedError($tmpFile, $cacheFile, $renameError)) {
            $this->reportFailure('replace_artifact', $cacheFile, $renameError);
            $this->removeTemporaryFile($tmpFile, $cacheFile);

            return false;
        }

        return true;
    }

    private function isSafeTarget(string $file): bool
    {
        if (is_link($file)) {
            return false;
        }

        if (! file_exists($file)) {
            return true;
        }

        $error = null;
        $stat  = $this->lstatWithCapturedError($file, $error);
        if (false === $stat) {
            return false;
        }

        return (($stat['mode'] ?? 0) & 0o170000) === 0o100000;
    }

    private function removeTemporaryFile(string $temporaryFile, string $cacheFile): void
    {
        $unlinkError = null;
        if (! $this->unlinkWithCapturedError($temporaryFile, $unlinkError)) {
            $this->reportFailure('remove_temporary_file', $cacheFile, $unlinkError);
        }
    }

    private function reportFailure(string $operation, string $cacheFile, ?string $error): void
    {
        if (! $this->logger instanceof LoggerInterface) {
            return;
        }

        try {
            $this->logger->error('Unable to write compiled route cache artifact.', [
                'operation'  => $operation,
                'cache_file' => $cacheFile,
                'error'      => $error ?? 'Unknown filesystem error.',
            ]);
        } catch (Throwable) {
            // Logging is optional and must never interfere with application boot.
        }
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

    private function mkdirWithCapturedError(string $directory, ?string &$error): bool
    {
        return $this->withCapturedError(fn () => mkdir($directory, 0o775, true), $error);
    }

    private function filePutContentsWithCapturedError(string $file, string $content, ?string &$error): bool|int
    {
        return $this->withCapturedError(fn () => file_put_contents($file, $content), $error);
    }

    /** @return array<string, int>|false */
    private function lstatWithCapturedError(string $file, ?string &$error): array|false
    {
        return $this->withCapturedError(fn () => lstat($file), $error);
    }

    private function renameWithCapturedError(string $source, string $target, ?string &$error): bool
    {
        return $this->withCapturedError(fn () => rename($source, $target), $error);
    }

    private function tempnamWithCapturedError(string $directory, ?string &$error): false|string
    {
        return $this->withCapturedError(fn () => tempnam($directory, '.routing-attributes-'), $error);
    }

    private function unlinkWithCapturedError(string $file, ?string &$error): bool
    {
        return $this->withCapturedError(fn () => unlink($file), $error);
    }
}
