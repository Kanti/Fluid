<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Tests\Unit\Core\Parser\Lexer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3Fluid\Fluid\Core\Parser\Lexer\ShorthandToken;
use TYPO3Fluid\Fluid\Core\Parser\Lexer\ShorthandTokenizer;

final class ShorthandTokenizerTest extends TestCase
{
    public static function normalContextCases(): array
    {
        return [
            'simple shorthand' => [
                'before {variable} after',
                [
                    ['text', 'before ', 'before '],
                    ['shorthand', '{variable}', '{variable}'],
                    ['text', ' after', ' after'],
                ],
            ],
            'nested shorthand' => [
                'abc {f:if(condition: {value}, then: \'x\')} def',
                [
                    ['text', 'abc ', 'abc '],
                    ['shorthand', '{f:if(condition: {value}, then: \'x\')}', '{f:if(condition: {value}, then: \'x\')}'],
                    ['text', ' def', ' def'],
                ],
            ],
            'quoted braces' => [
                'abc {f:for(bla:"post{{")} def',
                [
                    ['text', 'abc ', 'abc '],
                    ['shorthand', '{f:for(bla:"post{{")}', '{f:for(bla:"post{{")}'],
                    ['text', ' def', ' def'],
                ],
            ],
            'malformed shorthand stays text' => [
                'abc {f:if(condition: value) def',
                [
                    ['text', 'abc {f:if(condition: value) def', 'abc {f:if(condition: value) def'],
                ],
            ],
        ];
    }

    public static function cdataContextCases(): array
    {
        return [
            'simple cdata shorthand' => [
                'some {{{content}}} within',
                [
                    ['text', 'some ', 'some '],
                    ['shorthand', '{{{content}}}', '{content}'],
                    ['text', ' within', ' within'],
                ],
            ],
            'nested cdata shorthand' => [
                'x {{{f:format.trim(value: \'{{{content}}}\')}}} y',
                [
                    ['text', 'x ', 'x '],
                    ['shorthand', '{{{f:format.trim(value: \'{{{content}}}\')}}}', '{f:format.trim(value: \'{{{content}}}\')}'],
                    ['text', ' y', ' y'],
                ],
            ],
        ];
    }

    #[DataProvider('normalContextCases')]
    #[Test]
    public function tokenizeNormalContextReturnsExpectedSections(string $input, array $expected): void
    {
        $subject = new ShorthandTokenizer();

        $tokens = $subject->tokenize($input, ShorthandTokenizer::CONTEXT_NORMAL);

        self::assertSame($expected, array_map(
            static fn(ShorthandToken $token): array => [$token->type, $token->source, $token->normalizedSource],
            $tokens,
        ));
    }

    #[DataProvider('cdataContextCases')]
    #[Test]
    public function tokenizeCdataContextReturnsExpectedSections(string $input, array $expected): void
    {
        $subject = new ShorthandTokenizer();

        $tokens = $subject->tokenize($input, ShorthandTokenizer::CONTEXT_CDATA);

        self::assertSame($expected, array_map(
            static fn(ShorthandToken $token): array => [$token->type, $token->source, $token->normalizedSource],
            $tokens,
        ));
    }
}
