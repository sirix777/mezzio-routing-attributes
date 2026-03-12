<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Attributes\Discovery;

use const T_CLASS;
use const T_COMMENT;
use const T_DOC_COMMENT;
use const T_DOUBLE_COLON;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;
use const T_NAMESPACE;
use const T_NEW;
use const T_NS_SEPARATOR;
use const T_STRING;
use const T_WHITESPACE;

use function count;
use function file_get_contents;
use function is_array;
use function token_get_all;

final class PhpClassNameParser
{
    /**
     * @return list<non-empty-string>
     */
    public function parse(string $file): array
    {
        $content = file_get_contents($file);
        if (false === $content) {
            return [];
        }

        $tokens = token_get_all($content);
        $namespace = '';
        $classes = [];

        $count = count($tokens);
        for ($index = 0; $index < $count; ++$index) {
            $token = $tokens[$index];
            if (! is_array($token)) {
                continue;
            }

            if (T_NAMESPACE === $token[0]) {
                $namespace = $this->readNamespace($tokens, $index);

                continue;
            }

            if (T_CLASS !== $token[0]) {
                continue;
            }

            if ($this->isClassConstantFetch($tokens, $index)) {
                continue;
            }

            if ($this->isAnonymousClass($tokens, $index)) {
                continue;
            }

            $className = $this->readClassName($tokens, $index);
            if (null === $className) {
                continue;
            }

            $fqcn = '' === $namespace ? $className : $namespace . '\\' . $className;
            if ('' === $fqcn) {
                continue;
            }

            $classes[] = $fqcn;
        }

        return $classes;
    }

    /**
     * @param list<array{int, string, int}|int|string> $tokens
     */
    private function readNamespace(array $tokens, int &$index): string
    {
        $namespace = '';
        $count = count($tokens);

        for ($cursor = $index + 1; $cursor < $count; ++$cursor) {
            $token = $tokens[$cursor];
            if (';' === $token || '{' === $token) {
                $index = $cursor;

                break;
            }

            if (! is_array($token)) {
                continue;
            }

            if (
                T_STRING === $token[0]
                || T_NAME_QUALIFIED === $token[0]
                || T_NAME_FULLY_QUALIFIED === $token[0]
                || T_NS_SEPARATOR === $token[0]
            ) {
                $namespace .= $token[1];
            }
        }

        return $namespace;
    }

    /**
     * @param list<array{int, string, int}|int|string> $tokens
     */
    private function readClassName(array $tokens, int $index): ?string
    {
        $count = count($tokens);
        for ($cursor = $index + 1; $cursor < $count; ++$cursor) {
            $token = $tokens[$cursor];

            if ('{' === $token || '(' === $token || ';' === $token || ':' === $token || ']' === $token || ')' === $token) {
                return null;
            }

            if (! is_array($token)) {
                continue;
            }

            if (T_WHITESPACE === $token[0]) {
                continue;
            }

            if (T_COMMENT === $token[0]) {
                continue;
            }

            if (T_DOC_COMMENT === $token[0]) {
                continue;
            }

            if (T_STRING === $token[0]) {
                return $token[1];
            }

            return null;
        }

        return null;
    }

    /**
     * @param list<array{int, string, int}|int|string> $tokens
     */
    private function isAnonymousClass(array $tokens, int $index): bool
    {
        for ($cursor = $index - 1; $cursor >= 0; --$cursor) {
            $token = $tokens[$cursor];
            if (! is_array($token)) {
                continue;
            }

            if (T_WHITESPACE === $token[0]) {
                continue;
            }

            if (T_COMMENT === $token[0]) {
                continue;
            }

            if (T_DOC_COMMENT === $token[0]) {
                continue;
            }

            return T_NEW === $token[0];
        }

        return false;
    }

    /**
     * @param list<array{int, string, int}|int|string> $tokens
     */
    private function isClassConstantFetch(array $tokens, int $index): bool
    {
        for ($cursor = $index - 1; $cursor >= 0; --$cursor) {
            $token = $tokens[$cursor];
            if (! is_array($token)) {
                continue;
            }

            if (T_WHITESPACE === $token[0]) {
                continue;
            }

            if (T_COMMENT === $token[0]) {
                continue;
            }

            if (T_DOC_COMMENT === $token[0]) {
                continue;
            }

            return T_DOUBLE_COLON === $token[0];
        }

        return false;
    }
}
