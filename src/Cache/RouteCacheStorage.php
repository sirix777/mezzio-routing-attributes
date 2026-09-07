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
            $mkdirResult = CapturedError::run(fn () => mkdir($directory, 0o775, true), $mkdirError);
            if (! $mkdirResult && ! is_dir($directory)) {
                $this->reportFailure('create_directory', $cacheFile, $mkdirError);

                return false;
            }
        }

        $temporaryError  = null;
        $tmpFile         = CapturedError::run(fn () => tempnam($directory, '.routing-attributes-'), $temporaryError);
        if (false === $tmpFile || ! $this->isSafeTarget($tmpFile)) {
            $this->reportFailure('create_temporary_file', $cacheFile, $temporaryError);

            return false;
        }

        $writeError = null;
        $written    = CapturedError::run(fn () => file_put_contents($tmpFile, $content), $writeError);
        if (false === $written || strlen($content) !== $written) {
            $this->reportFailure('write_temporary_file', $cacheFile, $writeError);
            $this->removeTemporaryFile($tmpFile, $cacheFile);

            return false;
        }

        $replacementError = null;
        if (! $this->replaceTemporaryFile($tmpFile, $cacheFile, $replacementError)) {
            $this->reportFailure('replace_artifact', $cacheFile, $replacementError);
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
        $stat  = CapturedError::run(fn () => lstat($file), $error);
        if (false === $stat) {
            return false;
        }

        return ($stat['mode'] & 0o170000) === 0o100000;
    }

    private function removeTemporaryFile(string $temporaryFile, string $cacheFile): void
    {
        $unlinkError = null;
        if (! CapturedError::run(fn () => unlink($temporaryFile), $unlinkError)) {
            $this->reportFailure('remove_temporary_file', $cacheFile, $unlinkError);
        }
    }

    private function replaceTemporaryFile(string $temporaryFile, string $cacheFile, ?string &$error): bool
    {
        if ('Windows' === PHP_OS_FAMILY && file_exists($cacheFile)) {
            if (! $this->isSafeTarget($cacheFile)) {
                $error = 'Cache target changed to an unsafe type before replacement.';

                return false;
            }

            if (! CapturedError::run(fn () => unlink($cacheFile), $error)) {
                return false;
            }
        }

        return CapturedError::run(fn () => rename($temporaryFile, $cacheFile), $error);
    }

    private function reportFailure(string $operation, string $cacheFile, ?string $error): void
    {
        try {
            $this->logger?->error('Unable to write compiled route cache artifact.', [
                'operation'  => $operation,
                'cache_file' => $cacheFile,
                'error'      => $error ?? 'Unknown filesystem error.',
            ]);
        } catch (Throwable) {
            // Logging is optional and must never interfere with application boot.
        }
    }
}
