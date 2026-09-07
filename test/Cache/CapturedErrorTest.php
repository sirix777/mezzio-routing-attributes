<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Routing\Attributes\Cache;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Cache\CapturedError;

use function restore_error_handler;
use function set_error_handler;
use function trigger_error;

final class CapturedErrorTest extends TestCase
{
    public function testSuccessfulCallPreservesResultAndClearsPreviousError(): void
    {
        $error = 'Previous error';

        self::assertSame(42, CapturedError::run(static fn (): int => 42, $error));
        self::assertNull($error);
    }

    public function testWarningIsCapturedAndPreviousHandlerIsRestored(): void
    {
        $messages = [];
        set_error_handler(static function(int $severity, string $message) use (&$messages): bool {
            $messages[] = $message;

            return true;
        });

        try {
            $error  = null;
            CapturedError::run(static function(): bool {
                trigger_error('Captured warning', E_USER_WARNING);

                return false;
            }, $error);

            self::assertSame('Captured warning', $error);
            self::assertSame([], $messages);

            trigger_error('After capture', E_USER_WARNING);
            self::assertSame(['After capture'], $messages);
        } finally {
            restore_error_handler();
        }
    }

    public function testExceptionPropagatesAndPreviousHandlerIsRestored(): void
    {
        $messages = [];
        set_error_handler(static function(int $severity, string $message) use (&$messages): bool {
            $messages[] = $message;

            return true;
        });

        try {
            $error     = null;
            $exception = new RuntimeException('Failed callback');
            $this->expectExceptionObject($exception);

            try {
                CapturedError::run(static function() use ($exception): never {
                    throw $exception;
                }, $error);
            } finally {
                trigger_error('After exception', E_USER_WARNING);
                self::assertSame(['After exception'], $messages);
            }
        } finally {
            restore_error_handler();
        }
    }
}
