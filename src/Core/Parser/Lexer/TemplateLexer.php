<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Core\Parser\Lexer;

final class TemplateLexer implements TemplateLexerInterface
{
    public function tokenize(string $templateSource): array
    {
        $tokens = [];
        $cursor = 0;
        $textStart = 0;
        $sourceLength = strlen($templateSource);

        while ($cursor < $sourceLength) {
            if ($templateSource[$cursor] === '<') {
                $token = $this->scanCdata($templateSource, $cursor)
                    ?? $this->scanClosingViewHelperTag($templateSource, $cursor)
                    ?? $this->scanOpeningViewHelperTag($templateSource, $cursor);

                if ($token instanceof TemplateToken) {
                    if ($cursor > $textStart) {
                        array_push($tokens, ...$this->tokenizeTextSegment(substr($templateSource, $textStart, $cursor - $textStart), false));
                    }

                    if ($token->type === TemplateToken::TYPE_CDATA) {
                        array_push($tokens, ...$this->tokenizeTextSegment($token->content ?? '', true));
                    } else {
                        $tokens[] = $token;
                    }

                    $cursor += strlen($token->source);
                    $textStart = $cursor;
                    continue;
                }
            }

            $cursor++;
        }

        if ($textStart < $sourceLength) {
            array_push($tokens, ...$this->tokenizeTextSegment(substr($templateSource, $textStart), false));
        }

        return $tokens;
    }

    /**
     * @return list<TemplateToken>
     */
    private function tokenizeTextSegment(string $text, bool $insideCdata): array
    {
        $tokens = [];
        $cursor = 0;
        $textStart = 0;
        $length = strlen($text);

        while ($cursor < $length) {
            $token = $insideCdata
                ? $this->scanCdataShorthand($text, $cursor)
                : $this->scanShorthand($text, $cursor);

            if (!$token instanceof TemplateToken) {
                $cursor++;
                continue;
            }

            if ($cursor > $textStart) {
                $tokens[] = TemplateToken::text(substr($text, $textStart, $cursor - $textStart), $insideCdata);
            }
            $tokens[] = $token;
            $cursor += strlen($token->source);
            $textStart = $cursor;
        }

        if ($textStart < $length) {
            $tokens[] = TemplateToken::text(substr($text, $textStart), $insideCdata);
        }

        return $tokens;
    }

    private function scanCdata(string $templateSource, int $offset): ?TemplateToken
    {
        $prefix = '<![CDATA[';
        if (!str_starts_with(substr($templateSource, $offset), $prefix)) {
            return null;
        }

        $endPosition = strpos($templateSource, ']]>', $offset + strlen($prefix));
        if ($endPosition === false) {
            return null;
        }

        $source = substr($templateSource, $offset, $endPosition + 3 - $offset);
        return TemplateToken::cdata($source, substr($source, strlen($prefix), -3));
    }

    private function scanClosingViewHelperTag(string $templateSource, int $offset): ?TemplateToken
    {
        if (!str_starts_with(substr($templateSource, $offset), '</')) {
            return null;
        }

        $cursor = $offset + 2;
        $namespaceIdentifier = $this->consumeWhile($templateSource, $cursor, static fn(string $char): bool => self::isNamespaceCharacter($char));
        if (!isset($templateSource[$cursor]) || $templateSource[$cursor] !== ':') {
            return null;
        }
        $cursor++;

        $methodIdentifier = $this->consumeWhile($templateSource, $cursor, static fn(string $char): bool => self::isMethodCharacter($char));
        if ($methodIdentifier === '') {
            return null;
        }

        while (isset($templateSource[$cursor]) && ctype_space($templateSource[$cursor])) {
            $cursor++;
        }

        if (!isset($templateSource[$cursor]) || $templateSource[$cursor] !== '>') {
            return null;
        }

        $source = substr($templateSource, $offset, $cursor + 1 - $offset);
        return TemplateToken::closeViewHelperTag($source, $namespaceIdentifier, $methodIdentifier);
    }

    private function scanOpeningViewHelperTag(string $templateSource, int $offset): ?TemplateToken
    {
        if (!isset($templateSource[$offset + 1]) || $templateSource[$offset + 1] === '/' || $templateSource[$offset + 1] === '!') {
            return null;
        }

        $cursor = $offset + 1;
        $namespaceIdentifier = $this->consumeWhile($templateSource, $cursor, static fn(string $char): bool => self::isNamespaceCharacter($char));
        if (!isset($templateSource[$cursor]) || $templateSource[$cursor] !== ':') {
            return null;
        }
        $cursor++;

        $methodIdentifier = $this->consumeWhile($templateSource, $cursor, static fn(string $char): bool => self::isMethodCharacter($char));
        if ($methodIdentifier === '') {
            return null;
        }

        $attributesStart = null;
        $attributesEnd = $cursor;
        $tagAttributes = [];
        while (true) {
            $whitespaceStart = $cursor;
            while (isset($templateSource[$cursor]) && ctype_space($templateSource[$cursor])) {
                $cursor++;
            }

            if (!isset($templateSource[$cursor])) {
                return null;
            }

            if ($templateSource[$cursor] === '/' || $templateSource[$cursor] === '>') {
                if ($attributesStart !== null) {
                    $attributesEnd = $cursor;
                }
                break;
            }
            if ($attributesStart === null) {
                $attributesStart = $whitespaceStart;
            }

            $attributeName = $this->consumeWhile($templateSource, $cursor, static fn(string $char): bool => self::isAttributeNameCharacter($char));
            if ($attributeName === '') {
                return null;
            }

            while (isset($templateSource[$cursor]) && ctype_space($templateSource[$cursor])) {
                $cursor++;
            }
            if (!isset($templateSource[$cursor]) || $templateSource[$cursor] !== '=') {
                return null;
            }
            $cursor++;

            while (isset($templateSource[$cursor]) && ctype_space($templateSource[$cursor])) {
                $cursor++;
            }
            if (!isset($templateSource[$cursor]) || ($templateSource[$cursor] !== '"' && $templateSource[$cursor] !== '\'')) {
                return null;
            }

            $quotedValueStart = $cursor;
            $quote = $templateSource[$cursor];
            $cursor++;
            while (isset($templateSource[$cursor])) {
                if ($templateSource[$cursor] === '\\') {
                    $cursor += 2;
                    continue;
                }
                if ($templateSource[$cursor] === $quote) {
                    $cursor++;
                    break;
                }
                $cursor++;
            }

            if (!isset($templateSource[$cursor - 1]) || $templateSource[$cursor - 1] !== $quote) {
                return null;
            }
            $quotedValue = substr($templateSource, $quotedValueStart, $cursor - $quotedValueStart);
            $tagAttributes[] = new TagAttribute(
                $attributeName,
                $quotedValue,
                $this->unquoteString($quotedValue),
            );
            $attributesEnd = $cursor;
        }

        $attributes = $attributesStart === null ? '' : substr($templateSource, $attributesStart, $attributesEnd - $attributesStart);
        $selfClosing = false;
        if ($templateSource[$cursor] === '/') {
            $selfClosing = true;
            $cursor++;
        }
        if (!isset($templateSource[$cursor]) || $templateSource[$cursor] !== '>') {
            return null;
        }

        $source = substr($templateSource, $offset, $cursor + 1 - $offset);
        return TemplateToken::openViewHelperTag($source, $namespaceIdentifier, $methodIdentifier, $attributes, $tagAttributes, $selfClosing);
    }

    private function scanShorthand(string $text, int $offset): ?TemplateToken
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
                    return TemplateToken::shorthand($source, $source, false);
                }
                continue;
            }
            $cursor++;
        }

        return null;
    }

    private function scanCdataShorthand(string $text, int $offset): ?TemplateToken
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
                    return TemplateToken::shorthand($source, substr($source, 2, -2), true);
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

    private function consumeWhile(string $templateSource, int &$cursor, \Closure $matcher): string
    {
        $start = $cursor;
        while (isset($templateSource[$cursor]) && $matcher($templateSource[$cursor])) {
            $cursor++;
        }
        return substr($templateSource, $start, $cursor - $start);
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

    private static function isNamespaceCharacter(string $char): bool
    {
        return ctype_alnum($char) || $char === '.';
    }

    private static function isMethodCharacter(string $char): bool
    {
        return ctype_alnum($char) || $char === '.';
    }

    private static function isAttributeNameCharacter(string $char): bool
    {
        return ctype_alnum($char) || $char === ':' || $char === '-';
    }

    private function unquoteString(string $quotedValue): string
    {
        $value = $quotedValue;
        if ($value === '') {
            return $value;
        }
        if ($quotedValue[0] === '"') {
            $value = str_replace('\\"', '"', preg_replace('/(^"|"$)/', '', $quotedValue));
        } elseif ($quotedValue[0] === '\'') {
            $value = str_replace("\\'", "'", preg_replace('/(^\'|\'$)/', '', $quotedValue));
        }
        return str_replace('\\\\', '\\', $value);
    }
}
