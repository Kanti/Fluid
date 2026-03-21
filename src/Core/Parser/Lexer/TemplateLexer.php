<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Core\Parser\Lexer;

use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\Expression\CastingExpressionNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\Expression\MathExpressionNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\Expression\TernaryExpressionNode;

final class TemplateLexer implements TemplateLexerInterface
{
    private const SHORTHAND_IDENTIFIER_CHARACTERS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-';
    private const NAMESPACE_CHARACTERS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789.';
    private const METHOD_CHARACTERS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789.';
    private const ATTRIBUTE_NAME_CHARACTERS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789:-';
    private const CDATA_PREFIX = '<![CDATA[';
    private const CDATA_SUFFIX = ']]>';

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
                        array_push($tokens, ...$this->tokenizeTextRange($templateSource, $textStart, $cursor, false));
                    }

                    if ($token->type === TemplateToken::TYPE_CDATA) {
                        array_push(
                            $tokens,
                            ...$this->tokenizeTextRange(
                                $templateSource,
                                $cursor + strlen(self::CDATA_PREFIX),
                                $cursor + strlen($token->source) - strlen(self::CDATA_SUFFIX),
                                true,
                            ),
                        );
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
            array_push($tokens, ...$this->tokenizeTextRange($templateSource, $textStart, $sourceLength, false));
        }

        return $tokens;
    }

    /**
     * @return list<TemplateToken>
     */
    private function tokenizeTextRange(string $templateSource, int $start, int $end, bool $insideCdata): array
    {
        $tokens = [];
        $cursor = $start;
        $textStart = $start;

        while ($cursor < $end) {
            $token = $insideCdata
                ? $this->scanCdataShorthand($templateSource, $cursor, $end)
                : $this->scanShorthand($templateSource, $cursor, $end);

            if (!$token instanceof TemplateToken) {
                $cursor++;
                continue;
            }

            if ($cursor > $textStart) {
            $tokens[] = $this->createTextToken($templateSource, $textStart, $cursor, $insideCdata);
        }
        $tokens[] = $token;
        $cursor += strlen($token->source);
        $textStart = $cursor;
    }

        if ($textStart < $end) {
            $tokens[] = $this->createTextToken($templateSource, $textStart, $end, $insideCdata);
        }

        return $tokens;
    }

    private function createTextToken(string $templateSource, int $start, int $end, bool $insideCdata): TemplateToken
    {
        return TemplateToken::text(
            $this->sliceSource($templateSource, $start, $end),
            $insideCdata,
            $this->lineNumberAt($templateSource, $start),
        );
    }

    private function scanCdata(string $templateSource, int $offset): ?TemplateToken
    {
        if (substr_compare($templateSource, self::CDATA_PREFIX, $offset, strlen(self::CDATA_PREFIX)) !== 0) {
            return null;
        }

        $endPosition = strpos($templateSource, ']]>', $offset + strlen(self::CDATA_PREFIX));
        if ($endPosition === false) {
            return null;
        }

        $end = $endPosition + strlen(self::CDATA_SUFFIX);
        $source = $this->sliceSource($templateSource, $offset, $end);
        return TemplateToken::cdata(
            $source,
            substr($source, strlen(self::CDATA_PREFIX), -3),
            $this->lineNumberAt($templateSource, $offset),
        );
    }

    private function scanClosingViewHelperTag(string $templateSource, int $offset): ?TemplateToken
    {
        if (($templateSource[$offset] ?? null) !== '<' || ($templateSource[$offset + 1] ?? null) !== '/') {
            return null;
        }

        $cursor = $offset + 2;
        $namespaceIdentifier = $this->consumeSpan($templateSource, $cursor, self::NAMESPACE_CHARACTERS);
        if (!isset($templateSource[$cursor]) || $templateSource[$cursor] !== ':') {
            return null;
        }
        $cursor++;

        $methodIdentifier = $this->consumeSpan($templateSource, $cursor, self::METHOD_CHARACTERS);
        if ($methodIdentifier === '') {
            return null;
        }

        while (isset($templateSource[$cursor]) && ctype_space($templateSource[$cursor])) {
            $cursor++;
        }

        if (!isset($templateSource[$cursor]) || $templateSource[$cursor] !== '>') {
            return null;
        }

        $source = $this->sliceSource($templateSource, $offset, $cursor + 1);
        return TemplateToken::closeViewHelperTag(
            $source,
            $namespaceIdentifier,
            $methodIdentifier,
            $this->lineNumberAt($templateSource, $offset),
        );
    }

    private function scanOpeningViewHelperTag(string $templateSource, int $offset): ?TemplateToken
    {
        if (!isset($templateSource[$offset + 1]) || $templateSource[$offset + 1] === '/' || $templateSource[$offset + 1] === '!') {
            return null;
        }

        $cursor = $offset + 1;
        $identifier = $this->parseViewHelperIdentifier($templateSource, $cursor);
        if ($identifier === null) {
            return null;
        }

        $attributes = $this->parseTagAttributes($templateSource, $cursor);
        if ($attributes === null) {
            return null;
        }

        return $this->createOpeningTagToken($templateSource, $offset, $cursor, $identifier, $attributes);
    }

    /**
     * @return array{namespaceIdentifier: string, methodIdentifier: string}|null
     */
    private function parseViewHelperIdentifier(string $templateSource, int &$cursor): ?array
    {
        $namespaceIdentifier = $this->consumeSpan($templateSource, $cursor, self::NAMESPACE_CHARACTERS);
        if (!isset($templateSource[$cursor]) || $templateSource[$cursor] !== ':') {
            return null;
        }
        $cursor++;

        $methodIdentifier = $this->consumeSpan($templateSource, $cursor, self::METHOD_CHARACTERS);
        if ($methodIdentifier === '') {
            return null;
        }

        return [
            'namespaceIdentifier' => $namespaceIdentifier,
            'methodIdentifier' => $methodIdentifier,
        ];
    }

    /**
     * @return array{attributes: string, tagAttributes: list<TagAttribute>}|null
     */
    private function parseTagAttributes(string $templateSource, int &$cursor): ?array
    {
        $attributesStart = null;
        $attributesEnd = $cursor;
        $tagAttributes = [];

        while (true) {
            $whitespaceStart = $cursor;
            $this->skipWhitespace($templateSource, $cursor);

            if (!isset($templateSource[$cursor])) {
                return null;
            }

            if ($this->isTagEndCharacter($templateSource[$cursor])) {
                if ($attributesStart !== null) {
                    $attributesEnd = $cursor;
                }
                break;
            }

            if ($attributesStart === null) {
                $attributesStart = $whitespaceStart;
            }

            $tagAttribute = $this->parseTagAttribute($templateSource, $cursor);
            if (!$tagAttribute instanceof TagAttribute) {
                return null;
            }

            $tagAttributes[] = $tagAttribute;
            $attributesEnd = $cursor;
        }

        return [
            'attributes' => $attributesStart === null ? '' : $this->sliceSource($templateSource, $attributesStart, $attributesEnd),
            'tagAttributes' => $tagAttributes,
        ];
    }

    private function parseTagAttribute(string $templateSource, int &$cursor): ?TagAttribute
    {
        $attributeName = $this->consumeSpan($templateSource, $cursor, self::ATTRIBUTE_NAME_CHARACTERS);
        if ($attributeName === '') {
            return null;
        }

        $this->skipWhitespace($templateSource, $cursor);
        if (!isset($templateSource[$cursor]) || $templateSource[$cursor] !== '=') {
            return null;
        }
        $cursor++;

        $this->skipWhitespace($templateSource, $cursor);
        $quotedValue = $this->parseQuotedAttributeValue($templateSource, $cursor);
        if ($quotedValue === null) {
            return null;
        }

        return new TagAttribute(
            $attributeName,
            $quotedValue,
            $this->unquoteStringValue($quotedValue),
        );
    }

    private function parseQuotedAttributeValue(string $templateSource, int &$cursor): ?string
    {
        $quotedValueStart = $cursor;
        $quotedValue = $this->parseQuotedString($templateSource, $cursor);
        if ($quotedValue === null) {
            $cursor = $quotedValueStart;
            return null;
        }

        return $quotedValue;
    }

    /**
     * @param array{namespaceIdentifier: string, methodIdentifier: string} $identifier
     * @param array{attributes: string, tagAttributes: list<TagAttribute>} $attributes
     */
    private function createOpeningTagToken(string $templateSource, int $offset, int &$cursor, array $identifier, array $attributes): ?TemplateToken
    {
        $selfClosing = false;
        if ($templateSource[$cursor] === '/') {
            $selfClosing = true;
            $cursor++;
        }
        if (!isset($templateSource[$cursor]) || $templateSource[$cursor] !== '>') {
            return null;
        }

        $source = $this->sliceSource($templateSource, $offset, $cursor + 1);
        return TemplateToken::openViewHelperTag(
            $source,
            $identifier['namespaceIdentifier'],
            $identifier['methodIdentifier'],
            $attributes['attributes'],
            $attributes['tagAttributes'],
            $selfClosing,
            $this->lineNumberAt($templateSource, $offset),
        );
    }

    private function isTagEndCharacter(string $character): bool
    {
        return $character === '/' || $character === '>';
    }

    private function scanShorthand(string $templateSource, int $offset, int $end): ?TemplateToken
    {
        return $this->scanBalancedShorthand($templateSource, $offset, $end, '{', '}', false);
    }

    private function scanCdataShorthand(string $templateSource, int $offset, int $end): ?TemplateToken
    {
        return $this->scanBalancedShorthand($templateSource, $offset, $end, '{{{', '}}}', true);
    }

    private function scanBalancedShorthand(
        string $templateSource,
        int $offset,
        int $end,
        string $openingDelimiter,
        string $closingDelimiter,
        bool $insideCdata,
    ): ?TemplateToken
    {
        $openingLength = strlen($openingDelimiter);
        $closingLength = strlen($closingDelimiter);
        if (substr_compare($templateSource, $openingDelimiter, $offset, $openingLength) !== 0) {
            return null;
        }

        $cursor = $offset + $openingLength;
        $depth = 1;
        while ($cursor < $end) {
            if (
                $openingLength === 3
                && $cursor + 3 <= $end
                && substr_compare($templateSource, $openingDelimiter, $cursor, 3) === 0
            ) {
                $depth++;
                $cursor += 3;
                continue;
            }
            if (
                $cursor + $closingLength <= $end
                && substr_compare($templateSource, $closingDelimiter, $cursor, $closingLength) === 0
            ) {
                $depth--;
                $cursor += $closingLength;
                if ($depth === 0) {
                    $source = $this->sliceSource($templateSource, $offset, $cursor);
                    $normalizedSource = $insideCdata ? substr($source, 2, -2) : $source;
                    return $this->classifyShorthandToken($templateSource, $source, $normalizedSource, $insideCdata, $offset, $cursor);
                }
                continue;
            }

            $character = $templateSource[$cursor];
            if ($character === '"' || $character === '\'') {
                $cursor = $this->skipQuotedString($templateSource, $cursor);
                continue;
            }

            if (!$insideCdata && $character === '{') {
                $depth++;
                $cursor++;
                continue;
            }
            $cursor++;
        }

        return null;
    }

    private function classifyShorthandToken(string $templateSource, string $source, string $normalizedSource, bool $insideCdata, int $start, int $end): ?TemplateToken
    {
        $lineNumber = $this->lineNumberAt($templateSource, $start);

        $token = $this->tryCreateArrayToken($source, $normalizedSource, $insideCdata, $lineNumber)
            ?? $this->tryCreateObjectAccessorToken($source, $normalizedSource, $insideCdata, $lineNumber)
            ?? $this->tryCreateExpressionToken($source, $normalizedSource, $insideCdata, $lineNumber);
        if ($token instanceof TemplateToken) {
            return $token;
        }

        if ($this->isEmptyShorthand($source, $insideCdata)) {
            return null;
        }

        return TemplateToken::shorthand($source, $normalizedSource, $insideCdata, $lineNumber);
    }

    private function isEmptyShorthand(string $source, bool $insideCdata): bool
    {
        return strlen($source) <= ($insideCdata ? 6 : 2);
    }

    private function tryCreateArrayToken(string $source, string $normalizedSource, bool $insideCdata = false, int $lineNumber = 1): ?TemplateToken
    {
        $innerSource = trim(substr($normalizedSource, 1, -1));
        if ($innerSource === '') {
            return TemplateToken::array($source, [], $lineNumber);
        }

        $arrayParts = $this->parseShorthandArrayParts($innerSource);
        if ($arrayParts === []) {
            return null;
        }

        return TemplateToken::array($source, $arrayParts, $lineNumber);
    }

    private function tryCreateObjectAccessorToken(string $source, string $normalizedSource, bool $insideCdata = false, int $lineNumber = 1): ?TemplateToken
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
            $lineNumber,
        );
    }

    private function tryCreateExpressionToken(string $source, string $normalizedSource, bool $insideCdata = false, int $lineNumber = 1): ?TemplateToken
    {
        $expressionNodeType = $this->detectExpressionNodeType($normalizedSource);
        if ($expressionNodeType === null) {
            return null;
        }

        return TemplateToken::expression(
            $source,
            $normalizedSource,
            $expressionNodeType,
            [
                0 => $normalizedSource,
                1 => $normalizedSource,
            ],
            $insideCdata,
            $lineNumber,
        );
    }

    /**
     * @return class-string|null
     */
    private function detectExpressionNodeType(string $normalizedSource): ?string
    {
        if (!$this->isWrappedInBraces($normalizedSource)) {
            return null;
        }

        if ($this->isCastingExpression($normalizedSource)) {
            return CastingExpressionNode::class;
        }
        if ($this->isMathExpression($normalizedSource)) {
            return MathExpressionNode::class;
        }
        if ($this->isTernaryExpression($normalizedSource)) {
            return TernaryExpressionNode::class;
        }

        return null;
    }

    private function isCastingExpression(string $normalizedSource): bool
    {
        $content = substr($normalizedSource, 1, -1);
        if ($content !== trim($content)) {
            return false;
        }

        $separatorPosition = $this->findTopLevelAsSeparator($content);
        if ($separatorPosition === null) {
            return false;
        }

        $left = substr($content, 0, $separatorPosition);
        $right = substr($content, $separatorPosition + 4);

        return $this->isValidCastingOperand($left) && $this->isValidCastingTarget($right);
    }

    private function isMathExpression(string $normalizedSource): bool
    {
        $content = trim(substr($normalizedSource, 1, -1));
        if ($content === '') {
            return false;
        }

        $cursor = 0;
        if (!$this->parseMathOperand($content, $cursor, true)) {
            return false;
        }

        $foundOperator = false;
        while (true) {
            $this->skipWhitespace($content, $cursor);
            $operator = $content[$cursor] ?? null;
            if (!in_array($operator, ['*', '+', '^', '/', '%', '-'], true)) {
                break;
            }

            $foundOperator = true;
            $cursor++;

            if (!$this->parseMathOperand($content, $cursor, true)) {
                return false;
            }
        }

        $this->skipWhitespace($content, $cursor);
        return $foundOperator && $cursor === strlen($content);
    }

    private function isTernaryExpression(string $normalizedSource): bool
    {
        $content = substr($normalizedSource, 1, -1);
        $questionPosition = $this->findTopLevelCharacter($content, '?');
        if ($questionPosition === null) {
            return false;
        }

        $colonPosition = $this->findTopLevelCharacter($content, ':', $questionPosition + 1);
        if ($colonPosition === null) {
            return false;
        }

        $condition = trim(substr($content, 0, $questionPosition));
        $then = trim(substr($content, $questionPosition + 1, $colonPosition - $questionPosition - 1));
        $else = trim(substr($content, $colonPosition + 1));

        return $condition !== '' && $else !== '' && $then !== '?';
    }

    private function findTopLevelAsSeparator(string $content): ?int
    {
        $length = strlen($content);
        $cursor = 0;
        while ($cursor < $length) {
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
                $cursor = $this->skipNestedParentheses($content, $cursor);
                continue;
            }
            if (
                $character === ' '
                && substr($content, $cursor, 4) === ' as '
                && $cursor > 0
                && isset($content[$cursor + 4])
            ) {
                return $cursor;
            }
            $cursor++;
        }

        return null;
    }

    private function findTopLevelCharacter(string $content, string $characterToFind, int $offset = 0): ?int
    {
        $length = strlen($content);
        $cursor = $offset;
        while ($cursor < $length) {
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
                $cursor = $this->skipNestedParentheses($content, $cursor);
                continue;
            }
            if ($character === $characterToFind) {
                return $cursor;
            }
            $cursor++;
        }

        return null;
    }

    private function parseMathOperand(string $content, int &$cursor, bool $allowSign = false): bool
    {
        $this->skipWhitespace($content, $cursor);
        $start = $cursor;

        if (
            $allowSign
            && isset($content[$cursor])
            && ($content[$cursor] === '+' || $content[$cursor] === '-')
        ) {
            $cursor++;
            $this->skipWhitespace($content, $cursor);
        }

        if (($content[$cursor] ?? null) === '(') {
            $end = $this->skipNestedParentheses($content, $cursor);
            if ($end <= $cursor + 1) {
                $cursor = $start;
                return false;
            }
            $inner = substr($content, $cursor + 1, $end - $cursor - 2);
            if (!$this->isMathExpression('{' . $inner . '}') && !$this->isTernaryExpression('{' . $inner . '}')) {
                $cursor = $start;
                return false;
            }
            $cursor = $end;
            return true;
        }

        $number = $this->parseSignedMathNumber($content, $cursor);
        if ($number !== null) {
            return true;
        }

        $variableIdentifier = $this->parseShorthandVariableIdentifier($content, $cursor);
        if ($variableIdentifier !== null) {
            return true;
        }

        if (($content[$cursor] ?? null) === '{') {
            $cursor = $this->skipNestedBraces($content, $cursor);
            return true;
        }

        $cursor = $start;
        return false;
    }

    private function parseSignedMathNumber(string $content, int &$cursor): ?string
    {
        $start = $cursor;
        if (($content[$cursor] ?? null) === '+' || ($content[$cursor] ?? null) === '-') {
            $cursor++;
            $this->skipWhitespace($content, $cursor);
        }

        $number = $this->parseShorthandNumber($content, $cursor);
        if ($number === null) {
            $cursor = $start;
            return null;
        }

        return substr($content, $start, $cursor - $start);
    }

    private function isValidCastingOperand(string $content): bool
    {
        $cursor = 0;
        $operand = $this->parseShorthandVariableIdentifier($content, $cursor);
        return $operand !== null && $cursor === strlen($content);
    }

    private function isValidCastingTarget(string $content): bool
    {
        $content = trim($content);
        if ($content === '') {
            return false;
        }
        if (in_array($content, ['integer', 'boolean', 'string', 'float', 'array', 'DateTime'], true)) {
            return true;
        }

        $cursor = 0;
        $target = $this->parseShorthandVariableIdentifier($content, $cursor);
        return $target !== null && $cursor === strlen($content);
    }

    private function isWrappedInBraces(string $content): bool
    {
        return strlen($content) >= 2 && $content[0] === '{' && $content[strlen($content) - 1] === '}';
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
        $namespaceIdentifier = $this->consumeSpan($content, $cursor, self::NAMESPACE_CHARACTERS);
        if ($namespaceIdentifier === '' || !isset($content[$cursor]) || $content[$cursor] !== ':') {
            return null;
        }
        $cursor++;

        $methodIdentifier = $this->consumeSpan($content, $cursor, self::METHOD_CHARACTERS);
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

    private function skipNestedParentheses(string $content, int $offset): int
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

    private function consumeSpan(string $input, int &$cursor, string $allowedCharacters): string
    {
        $length = strspn($input, $allowedCharacters, $cursor);
        if ($length === 0) {
            return '';
        }

        $span = substr($input, $cursor, $length);
        $cursor += $length;
        return $span;
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
        if ($quotedValue === '') {
            return '';
        }

        $value = $quotedValue;
        $lastCharacter = $quotedValue[strlen($quotedValue) - 1];
        if (
            ($quotedValue[0] === '"' && $lastCharacter === '"')
            || ($quotedValue[0] === '\'' && $lastCharacter === '\'')
        ) {
            $value = substr($quotedValue, 1, -1);
        }

        if ($quotedValue[0] === '"') {
            $value = str_replace('\\"', '"', $value);
        } elseif ($quotedValue[0] === '\'') {
            $value = str_replace("\\'", "'", $value);
        }

        return str_replace('\\\\', '\\', $value);
    }

    private function sliceSource(string $templateSource, int $start, int $end): string
    {
        return substr($templateSource, $start, $end - $start);
    }

    private function lineNumberAt(string $templateSource, int $offset): int
    {
        if ($offset <= 0) {
            return 1;
        }

        return substr_count(substr($templateSource, 0, $offset), PHP_EOL) + 1;
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
