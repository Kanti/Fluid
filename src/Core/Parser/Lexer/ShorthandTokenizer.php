<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Core\Parser\Lexer;

final class ShorthandTokenizer
{
    public const CONTEXT_NORMAL = 'normal';
    public const CONTEXT_CDATA = 'cdata';

    /**
     * @return list<ShorthandToken>
     */
    public function tokenize(string $text, string $context = self::CONTEXT_NORMAL): array
    {
        $tokens = [];
        $cursor = 0;
        $textStart = 0;
        $length = strlen($text);

        while ($cursor < $length) {
            $token = $context === self::CONTEXT_CDATA
                ? $this->scanCdataShorthand($text, $cursor)
                : $this->scanShorthand($text, $cursor);

            if (!$token instanceof ShorthandToken) {
                $cursor++;
                continue;
            }

            if ($cursor > $textStart) {
                $tokens[] = ShorthandToken::text(substr($text, $textStart, $cursor - $textStart));
            }
            $tokens[] = $token;
            $cursor += strlen($token->source);
            $textStart = $cursor;
        }

        if ($textStart < $length) {
            $tokens[] = ShorthandToken::text(substr($text, $textStart));
        }

        return $tokens;
    }

    private function scanShorthand(string $text, int $offset): ?ShorthandToken
    {
        if (!isset($text[$offset]) || $text[$offset] !== '{') {
            return null;
        }

        $cursor = $offset + 1;
        $depth = 1;
        while (isset($text[$cursor])) {
            $character = $text[$cursor];
            if ($character === '"' || $character === '\'') {
                $cursor = $this->skipQuotedString($text, $cursor);
                continue;
            }
            if ($character === '{') {
                $depth++;
                $cursor++;
                continue;
            }
            if ($character === '}') {
                $depth--;
                $cursor++;
                if ($depth === 0) {
                    $source = substr($text, $offset, $cursor - $offset);
                    if (strlen($source) <= 2) {
                        return null;
                    }
                    return ShorthandToken::shorthand($source, $source);
                }
                continue;
            }
            $cursor++;
        }

        return null;
    }

    private function scanCdataShorthand(string $text, int $offset): ?ShorthandToken
    {
        if (substr($text, $offset, 3) !== '{{{') {
            return null;
        }

        $cursor = $offset + 3;
        $depth = 1;
        while (isset($text[$cursor])) {
            if (substr($text, $cursor, 3) === '{{{') {
                $depth++;
                $cursor += 3;
                continue;
            }
            if (substr($text, $cursor, 3) === '}}}') {
                $depth--;
                $cursor += 3;
                if ($depth === 0) {
                    $source = substr($text, $offset, $cursor - $offset);
                    if (strlen($source) <= 6) {
                        return null;
                    }
                    return ShorthandToken::shorthand($source, substr($source, 2, -2));
                }
                continue;
            }

            $character = $text[$cursor];
            if ($character === '"' || $character === '\'') {
                $cursor = $this->skipQuotedString($text, $cursor);
                continue;
            }
            $cursor++;
        }

        return null;
    }

    private function skipQuotedString(string $text, int $offset): int
    {
        $quote = $text[$offset];
        $cursor = $offset + 1;
        while (isset($text[$cursor])) {
            if ($text[$cursor] === '\\') {
                $cursor += 2;
                continue;
            }
            $cursor++;
            if ($text[$cursor - 1] === $quote) {
                return $cursor;
            }
        }
        return strlen($text);
    }
}
