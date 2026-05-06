<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Cache;

use function dirname;
use function file_put_contents;
use function is_array;
use function is_dir;
use function is_file;
use function mkdir;
use function rename;
use function restore_error_handler;
use function set_error_handler;
use function uniqid;
use function unlink;

final readonly class RouteCacheStorage
{
    public function save(string $cacheFile, string $content): bool
    {
        $directory = dirname($cacheFile);
        if (! is_dir($directory)) {
            $mkdirError = null;
            $mkdirResult = $this->mkdirWithCapturedError($directory, $mkdirError);
            if (! $mkdirResult && ! is_dir($directory)) {
                return false;
            }
        }

        $tmpFile = $cacheFile . '.tmp.' . uniqid('', true);
        $writeError = null;
        if (false === $this->filePutContentsWithCapturedError($tmpFile, $content, $writeError)) {
            return false;
        }

        $renameError = null;
        if (! $this->renameWithCapturedError($tmpFile, $cacheFile, $renameError)) {
            $unlinkError = null;
            $this->unlinkWithCapturedError($tmpFile, $unlinkError);
        }

        return true;
    }

    public function load(string $cacheFile): ?string
    {
        if (! is_file($cacheFile)) {
            return null;
        }

        $requireError = null;
        $payload = $this->requireWithCapturedError($cacheFile, $requireError);

        if (null === $requireError && is_array($payload)) {
            return $cacheFile;
        }

        return null;
    }

    public function exists(string $cacheFile): bool
    {
        return is_file($cacheFile);
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

    private function renameWithCapturedError(string $source, string $target, ?string &$error): bool
    {
        return $this->withCapturedError(fn () => rename($source, $target), $error);
    }

    private function requireWithCapturedError(string $file, ?string &$error): mixed
    {
        return $this->withCapturedError(fn () => require $file, $error);
    }

    private function unlinkWithCapturedError(string $file, ?string &$error): bool
    {
        return $this->withCapturedError(fn () => unlink($file), $error);
    }
}
