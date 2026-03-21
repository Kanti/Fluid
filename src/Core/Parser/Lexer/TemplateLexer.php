<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Core\Parser\Lexer;

final class TemplateLexer implements TemplateLexerInterface
{
    private const SHORTHAND_IDENTIFIER_CHARACTERS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-';

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
                $this->unquoteStringValue($quotedValue),
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
                    $arrayToken = $this->createArrayTokenFromShorthand($source, $source);
                    if ($arrayToken instanceof TemplateToken) {
                        return $arrayToken;
                    }
                    $objectAccessorToken = $this->createObjectAccessorTokenFromShorthand($source, $source);
                    if ($objectAccessorToken instanceof TemplateToken) {
                        return $objectAccessorToken;
                    }
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
                    $normalizedSource = substr($source, 2, -2);
                    $arrayToken = $this->createArrayTokenFromShorthand($source, $normalizedSource, true);
                    if ($arrayToken instanceof TemplateToken) {
                        return $arrayToken;
                    }
                    $objectAccessorToken = $this->createObjectAccessorTokenFromShorthand($source, $normalizedSource, true);
                    if ($objectAccessorToken instanceof TemplateToken) {
                        return $objectAccessorToken;
                    }
                    if (strlen($source) <= 6) {
                        return null;
                    }
                    return TemplateToken::shorthand($source, $normalizedSource, true);
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

    private function createArrayTokenFromShorthand(string $source, string $normalizedSource, bool $insideCdata = false): ?TemplateToken
    {
        $innerSource = trim(substr($normalizedSource, 1, -1));
        if ($innerSource === '') {
            return TemplateToken::array($source, []);
        }

        $arrayParts = $this->parseShorthandArrayParts($innerSource);
        if ($arrayParts === []) {
            return null;
        }

        return TemplateToken::array($source, $arrayParts);
    }

    private function createObjectAccessorTokenFromShorthand(string $source, string $normalizedSource, bool $insideCdata = false): ?TemplateToken
    {
        $content = substr($normalizedSource, 1, -1);
        if ($content !== trim($content)) {
            return null;
        }
        $parsed = $this->parseShorthandObjectAccessor($content);
        if ($parsed === null) {
            return null;
        }

        return TemplateToken::objectAccessor(
            $source,
            $normalizedSource,
            $parsed['objectAccessor'],
            $parsed['inlineViewHelpers'],
            $insideCdata,
        );
    }

    /**
     * @return array{objectAccessor: string, inlineViewHelpers: list<ShorthandInlineViewHelper>}|null
     */
    private function parseShorthandObjectAccessor(string $content): ?array
    {
        $content = trim($content);
        $length = strlen($content);
        if ($length === 0) {
            return null;
        }

        $cursor = 0;
        $objectAccessor = $this->parseObjectAccessorSegment($content, $cursor);
        if ($cursor >= $length) {
            return $objectAccessor === '' ? null : [
                'objectAccessor' => $objectAccessor,
                'inlineViewHelpers' => [],
            ];
        }

        $separator = $this->parseViewHelperSeparator($content, $cursor);
        if ($separator !== null) {
            $inlineViewHelpers = $this->parseInlineViewHelperChain($content, $cursor);
            if ($inlineViewHelpers === null || $cursor !== $length) {
                return null;
            }
            return [
                'objectAccessor' => $objectAccessor,
                'inlineViewHelpers' => $inlineViewHelpers,
            ];
        }

        $cursor = 0;
        $inlineViewHelpers = $this->parseInlineViewHelperChain($content, $cursor);
        if ($inlineViewHelpers === null || $cursor !== $length) {
            return null;
        }
        return [
            'objectAccessor' => '',
            'inlineViewHelpers' => $inlineViewHelpers,
        ];
    }

    private function parseObjectAccessorSegment(string $content, int &$cursor): string
    {
        $start = $cursor;
        while (isset($content[$cursor])) {
            if ($this->matchesViewHelperSeparator($content, $cursor)) {
                break;
            }

            $character = $content[$cursor];
            if (ctype_alnum($character) || $character === '_' || $character === '-' || $character === '.') {
                $cursor++;
                continue;
            }

            if ($character === '{') {
                $cursor = $this->skipNestedBraces($content, $cursor);
                continue;
            }

            break;
        }

        return trim(substr($content, $start, $cursor - $start));
    }

    /**
     * @return list<ShorthandInlineViewHelper>|null
     */
    private function parseInlineViewHelperChain(string $content, int &$cursor): ?array
    {
        $inlineViewHelpers = [];
        $viewHelper = $this->parseInlineViewHelper($content, $cursor);
        if (!$viewHelper instanceof ShorthandInlineViewHelper) {
            return null;
        }
        $inlineViewHelpers[] = $viewHelper;

        while (($separator = $this->parseViewHelperSeparator($content, $cursor)) !== null) {
            $viewHelper = $this->parseInlineViewHelper($content, $cursor);
            if (!$viewHelper instanceof ShorthandInlineViewHelper) {
                return null;
            }
            $inlineViewHelpers[] = $viewHelper;
        }

        return $inlineViewHelpers;
    }

    private function parseInlineViewHelper(string $content, int &$cursor): ?ShorthandInlineViewHelper
    {
        $namespaceIdentifier = $this->consumeWhile($content, $cursor, static fn(string $char): bool => self::isNamespaceCharacter($char));
        if ($namespaceIdentifier === '' || !isset($content[$cursor]) || $content[$cursor] !== ':') {
            return null;
        }
        $cursor++;

        $methodIdentifier = $this->consumeWhile($content, $cursor, static fn(string $char): bool => self::isMethodCharacter($char));
        if ($methodIdentifier === '' || !isset($content[$cursor]) || $content[$cursor] !== '(') {
            return null;
        }

        $argumentString = $this->parseInlineViewHelperArgumentString($content, $cursor);
        if ($argumentString === null) {
            return null;
        }

        $arguments = trim($argumentString) === '' ? [] : $this->parseShorthandArrayParts($argumentString);
        if (trim($argumentString) !== '' && $arguments === []) {
            return null;
        }

        return new ShorthandInlineViewHelper($namespaceIdentifier, $methodIdentifier, $arguments);
    }

    private function parseInlineViewHelperArgumentString(string $content, int &$cursor): ?string
    {
        if (!isset($content[$cursor]) || $content[$cursor] !== '(') {
            return null;
        }

        $start = $cursor + 1;
        $depth = 1;
        $cursor++;
        while (isset($content[$cursor])) {
            $character = $content[$cursor];
            if ($character === '"' || $character === '\'') {
                $cursor = $this->skipQuotedString($content, $cursor);
                continue;
            }
            if ($character === '{') {
                $cursor = $this->skipNestedBraces($content, $cursor);
                continue;
            }
            if ($character === '(') {
                $depth++;
                $cursor++;
                continue;
            }
            if ($character === ')') {
                $depth--;
                if ($depth === 0) {
                    $argumentString = substr($content, $start, $cursor - $start);
                    $cursor++;
                    return $argumentString;
                }
                $cursor++;
                continue;
            }
            $cursor++;
        }

        return null;
    }

    private function parseViewHelperSeparator(string $content, int &$cursor): ?string
    {
        $start = $cursor;
        while (isset($content[$cursor]) && ctype_space($content[$cursor])) {
            $cursor++;
        }

        if ($this->matchesViewHelperSeparator($content, $cursor)) {
            $separator = substr($content, $cursor, $content[$cursor] === '|' ? 1 : 2);
            $cursor += strlen($separator);
            while (isset($content[$cursor]) && ctype_space($content[$cursor])) {
                $cursor++;
            }
            return $separator;
        }

        $cursor = $start;
        return null;
    }

    private function matchesViewHelperSeparator(string $content, int $cursor): bool
    {
        return ($content[$cursor] ?? null) === '|'
            || substr($content, $cursor, 2) === '->';
    }

    private function skipNestedBraces(string $content, int $offset): int
    {
        $cursor = $offset + 1;
        $depth = 1;
        while (isset($content[$cursor])) {
            $character = $content[$cursor];
            if ($character === '"' || $character === '\'') {
                $cursor = $this->skipQuotedString($content, $cursor);
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
                    return $cursor;
                }
                continue;
            }
            $cursor++;
        }

        return strlen($content);
    }

    /**
     * @return list<ShorthandArrayPart>
     */
    private function parseShorthandArrayParts(string $arrayText): array
    {
        $cursor = 0;
        $length = strlen($arrayText);
        $parts = [];
        $this->skipWhitespace($arrayText, $cursor);
        if ($cursor >= $length) {
            return [];
        }

        while ($cursor < $length) {
            $part = $this->parseShorthandArrayPart($arrayText, $cursor);
            if (!$part instanceof ShorthandArrayPart) {
                return [];
            }
            $parts[] = $part;
            $whitespaceStart = $cursor;
            $this->skipWhitespace($arrayText, $cursor);
            if ($cursor >= $length) {
                return $parts;
            }
            if ($arrayText[$cursor] !== ',') {
                if ($cursor === $whitespaceStart) {
                    return [];
                }
                continue;
            }
            $cursor++;
            $this->skipWhitespace($arrayText, $cursor);
        }

        return $parts;
    }

    private function parseShorthandArrayPart(string $arrayText, int &$cursor): ?ShorthandArrayPart
    {
        $key = $this->parseShorthandArrayKey($arrayText, $cursor);
        if ($key === null) {
            return null;
        }
        $this->skipWhitespace($arrayText, $cursor);
        if (!isset($arrayText[$cursor]) || ($arrayText[$cursor] !== ':' && $arrayText[$cursor] !== '=')) {
            return null;
        }
        $cursor++;
        $this->skipWhitespace($arrayText, $cursor);

        $quotedString = $this->parseQuotedString($arrayText, $cursor);
        if ($quotedString !== null) {
            return new ShorthandArrayPart($key, quotedString: $quotedString);
        }

        $subarray = $this->parseShorthandSubarray($arrayText, $cursor);
        if ($subarray !== null) {
            return new ShorthandArrayPart($key, subarray: $subarray);
        }

        $expressionValue = $this->parseNestedShorthandExpression($arrayText, $cursor);
        if ($expressionValue !== null) {
            return new ShorthandArrayPart($key, expressionValue: $expressionValue);
        }

        $variableIdentifier = $this->parseShorthandVariableIdentifier($arrayText, $cursor);
        if ($variableIdentifier !== null) {
            return new ShorthandArrayPart($key, variableIdentifier: $variableIdentifier);
        }

        $number = $this->parseShorthandNumber($arrayText, $cursor);
        if ($number !== null) {
            return new ShorthandArrayPart($key, number: $number);
        }

        return null;
    }

    private function parseNestedShorthandExpression(string $arrayText, int &$cursor): ?string
    {
        if (($arrayText[$cursor] ?? null) !== '{') {
            return null;
        }

        $start = $cursor;
        $cursor = $this->skipNestedBraces($arrayText, $cursor);
        if ($cursor <= $start + 1) {
            $cursor = $start;
            return null;
        }

        return substr($arrayText, $start, $cursor - $start);
    }

    private function parseShorthandArrayKey(string $arrayText, int &$cursor): ?string
    {
        $quotedKey = $this->parseQuotedString($arrayText, $cursor);
        if ($quotedKey !== null) {
            return $this->unquoteStringValue($quotedKey) === '' ? null : $quotedKey;
        }
        return $this->parseCharacterSpan($arrayText, $cursor, self::SHORTHAND_IDENTIFIER_CHARACTERS);
    }

    private function parseShorthandSubarray(string $arrayText, int &$cursor): ?string
    {
        if (!isset($arrayText[$cursor]) || $arrayText[$cursor] !== '{') {
            return null;
        }
        $start = $cursor + 1;
        $depth = 1;
        $cursor++;
        while (isset($arrayText[$cursor])) {
            $quotedString = $this->parseQuotedString($arrayText, $cursor);
            if ($quotedString !== null) {
                continue;
            }
            if ($arrayText[$cursor] === '{') {
                $depth++;
                $cursor++;
                continue;
            }
            if ($arrayText[$cursor] === '}') {
                $depth--;
                if ($depth === 0) {
                    $subarray = substr($arrayText, $start, $cursor - $start);
                    $cursor++;
                    return trim($subarray);
                }
            }
            $cursor++;
        }
        return null;
    }

    private function parseShorthandNumber(string $arrayText, int &$cursor): ?string
    {
        $start = $cursor;
        $integerPartLength = strspn($arrayText, '0123456789', $cursor);
        if ($integerPartLength === 0) {
            return null;
        }
        $cursor += $integerPartLength;
        if (($arrayText[$cursor] ?? null) === '.') {
            $decimalPartLength = strspn($arrayText, '0123456789', $cursor + 1);
            if ($decimalPartLength > 0) {
                $cursor += $decimalPartLength + 1;
            }
        }
        return substr($arrayText, $start, $cursor - $start);
    }

    private function parseShorthandVariableIdentifier(string $arrayText, int &$cursor): ?string
    {
        $start = $cursor;
        $firstSegment = $this->parseCharacterSpan($arrayText, $cursor, self::SHORTHAND_IDENTIFIER_CHARACTERS);
        if ($firstSegment === null || strpbrk($firstSegment, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') === false) {
            $cursor = $start;
            return null;
        }
        while (($arrayText[$cursor] ?? null) === '.') {
            $cursor++;
            $segment = $this->parseCharacterSpan($arrayText, $cursor, self::SHORTHAND_IDENTIFIER_CHARACTERS);
            if ($segment === null) {
                $cursor = $start;
                return null;
            }
        }
        return substr($arrayText, $start, $cursor - $start);
    }

    private function consumeWhile(string $templateSource, int &$cursor, \Closure $matcher): string
    {
        $start = $cursor;
        while (isset($templateSource[$cursor]) && $matcher($templateSource[$cursor])) {
            $cursor++;
        }
        return substr($templateSource, $start, $cursor - $start);
    }

    private function parseQuotedString(string $input, int &$cursor): ?string
    {
        $quote = $input[$cursor] ?? null;
        if ($quote !== '"' && $quote !== '\'') {
            return null;
        }
        $start = $cursor;
        $cursor++;
        while (isset($input[$cursor])) {
            if ($input[$cursor] === '\\') {
                $cursor += 2;
                continue;
            }
            if ($input[$cursor] === $quote) {
                $cursor++;
                return substr($input, $start, $cursor - $start);
            }
            $cursor++;
        }
        $cursor = $start;
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

    private function parseCharacterSpan(string $input, int &$cursor, string $allowedCharacters): ?string
    {
        $spanLength = strspn($input, $allowedCharacters, $cursor);
        if ($spanLength === 0) {
            return null;
        }
        $span = substr($input, $cursor, $spanLength);
        $cursor += $spanLength;
        return $span;
    }

    private function skipWhitespace(string $input, int &$cursor): void
    {
        $cursor += strspn($input, " \t\r\n", $cursor);
    }

    private function unquoteStringValue(string $quotedValue): string
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
}
