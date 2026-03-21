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
            if ($templateSource[$cursor] !== '<') {
                $cursor++;
                continue;
            }

            $token = $this->scanCdata($templateSource, $cursor)
                ?? $this->scanClosingViewHelperTag($templateSource, $cursor)
                ?? $this->scanOpeningViewHelperTag($templateSource, $cursor);

            if (!$token instanceof TemplateToken) {
                $cursor++;
                continue;
            }

            if ($cursor > $textStart) {
                $tokens[] = TemplateToken::text(substr($templateSource, $textStart, $cursor - $textStart));
            }
            $tokens[] = $token;
            $cursor += strlen($token->source);
            $textStart = $cursor;
        }

        if ($textStart < $sourceLength) {
            $tokens[] = TemplateToken::text(substr($templateSource, $textStart));
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

    private function consumeWhile(string $templateSource, int &$cursor, \Closure $matcher): string
    {
        $start = $cursor;
        while (isset($templateSource[$cursor]) && $matcher($templateSource[$cursor])) {
            $cursor++;
        }
        return substr($templateSource, $start, $cursor - $start);
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
