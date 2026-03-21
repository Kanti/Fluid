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
use TYPO3Fluid\Fluid\Core\Parser\Lexer\ShorthandArrayPart;
use TYPO3Fluid\Fluid\Core\Parser\Lexer\ShorthandInlineViewHelper;
use TYPO3Fluid\Fluid\Core\Parser\Lexer\TagAttribute;
use TYPO3Fluid\Fluid\Core\Parser\Lexer\TemplateLexer;
use TYPO3Fluid\Fluid\Core\Parser\Lexer\TemplateToken;

final class TemplateLexerTest extends TestCase
{
    public static function templatesToTokenize(): array
    {
        return [
            ['TemplateParserTestFixture01-shorthand'],
            ['TemplateParserTestFixture06'],
            ['TemplateParserTestFixture14'],
        ];
    }

    public static function tokenizationCases(): array
    {
        return [
            'simple tags' => [
                '<html><head> <f:a.testing /> <f:blablubb> {testing}</f4:blz> </t3:hi.jo>',
            ],
            'attribute with greater than sign' => [
                'hi<f:testing attribute="Hallo>{yep}" nested:attribute="jup" />ja',
            ],
            'escaped double quote' => [
                'hi<f:testing attribute="Hallo\"{yep}" nested:attribute="jup" />ja',
            ],
            'single quoted attribute' => [
                'hi<f:testing attribute=\'Hallo>{yep}\' nested:attribute="jup" />ja',
            ],
            'escaped single quote' => [
                'hi<f:testing attribute=\'Hallo\\\'{yep}\' nested:attribute="jup" />ja',
            ],
            'cdata block' => [
                'Hallo <f:testing><![CDATA[<f:notparsed>]]></f:testing>',
            ],
            'many attributes' => [
                '<f:form enctype="multipart/form-data" onsubmit="void(0)" onreset="void(0)" action="someAction" arguments="{arg1: \'val1\', arg2: \'val2\'}" controller="someController" package="YourCompanyName.somePackage" subpackage="YourCompanyName.someSubpackage" section="someSection" format="txt" additionalParams="{param1: \'val1\', param2: \'val2\'}" absolute="true" addQueryString="true" argumentsToBeExcludedFromQueryString="{0: \'foo\'}" />Begin<f:form enctype="multipart/form-data" onsubmit="void(0)" onreset="void(0)" action="someAction" arguments="{arg1: \'val1\', arg2: \'val2\'}" controller="someController" package="YourCompanyName.somePackage" subpackage="YourCompanyName.someSubpackage" section="someSection" format="txt" additionalParams="{param1: \'val1\', param2: \'val2\'}" absolute="true" addQueryString="true" argumentsToBeExcludedFromQueryString="{0: \'foo\'}" />End',
            ],
            'data attribute' => [
                '<f:a.testing data-bar="foo"> <f:a.testing>',
            ],
            'custom namespace' => [
                '<foo:a.testing someArgument="bar"> <f:a.testing>',
            ],
            'dotted namespace' => [
                '<foo.bar:a.testing someArgument="baz"> <f:a.testing>',
            ],
        ];
    }

    public static function shorthandCases(): array
    {
        return [
            'simple shorthand' => [
                'before {variable} after',
                [
                    ['text', 'before ', 'before '],
                    ['object_accessor', '{variable}', '{variable}'],
                    ['text', ' after', ' after'],
                ],
            ],
            'nested shorthand' => [
                'abc {f:if(condition: {value}, then: \'x\')} def',
                [
                    ['text', 'abc ', 'abc '],
                    ['object_accessor', '{f:if(condition: {value}, then: \'x\')}', '{f:if(condition: {value}, then: \'x\')}'],
                    ['text', ' def', ' def'],
                ],
            ],
            'quoted braces' => [
                'abc {f:for(bla:"post{{")} def',
                [
                    ['text', 'abc ', 'abc '],
                    ['object_accessor', '{f:for(bla:"post{{")}', '{f:for(bla:"post{{")}'],
                    ['text', ' def', ' def'],
                ],
            ],
            'malformed shorthand stays text' => [
                'abc {f:if(condition: value) def',
                [
                    ['text', 'abc {f:if(condition: value) def', 'abc {f:if(condition: value) def'],
                ],
            ],
            'simple cdata shorthand' => [
                'some <![CDATA[{{{content}}}]]> within',
                [
                    ['text', 'some ', 'some '],
                    ['object_accessor', '{{{content}}}', '{content}'],
                    ['text', ' within', ' within'],
                ],
            ],
            'nested cdata shorthand' => [
                'x <![CDATA[{{{f:format.trim(value: \'{{{content}}}\')}}}]]> y',
                [
                    ['text', 'x ', 'x '],
                    ['object_accessor', '{{{f:format.trim(value: \'{{{content}}}\')}}}', '{f:format.trim(value: \'{{{content}}}\')}'],
                    ['text', ' y', ' y'],
                ],
            ],
        ];
    }

    public static function objectAccessorTokenCases(): array
    {
        return [
            'bare object accessor' => [
                '{object.recursive}',
                TemplateToken::objectAccessor('{object.recursive}', '{object.recursive}', 'object.recursive', []),
            ],
            'numeric prefixed object accessor' => [
                '{123numericprefix}',
                TemplateToken::objectAccessor('{123numericprefix}', '{123numericprefix}', '123numericprefix', []),
            ],
            'inline viewhelper only' => [
                '{f:for(each: bla)}',
                TemplateToken::objectAccessor('{f:for(each: bla)}', '{f:for(each: bla)}', '', [
                    new ShorthandInlineViewHelper('f', 'for', [
                        new ShorthandArrayPart('each', variableIdentifier: 'bla'),
                    ]),
                ]),
            ],
            'object accessor with chained inline viewhelpers' => [
                '{bla.blubb->f:for(param:42)->foo.bar:bla(a:"b\\"->(f:a()", cd: {a:b})}',
                TemplateToken::objectAccessor('{bla.blubb->f:for(param:42)->foo.bar:bla(a:"b\\"->(f:a()", cd: {a:b})}', '{bla.blubb->f:for(param:42)->foo.bar:bla(a:"b\\"->(f:a()", cd: {a:b})}', 'bla.blubb', [
                    new ShorthandInlineViewHelper('f', 'for', [
                        new ShorthandArrayPart('param', number: '42'),
                    ]),
                    new ShorthandInlineViewHelper('foo.bar', 'bla', [
                        new ShorthandArrayPart('a', quotedString: '"b\\"->(f:a()"'),
                        new ShorthandArrayPart('cd', subarray: 'a:b'),
                    ]),
                ]),
            ],
        ];
    }

    public static function shorthandArrayTokenCases(): array
    {
        return [
            'nested array parts' => [
                '{a: b, e: {c:d, "e#":f, \'g\': "h"}}',
                TemplateToken::array('{a: b, e: {c:d, "e#":f, \'g\': "h"}}', [
                    new ShorthandArrayPart('a', variableIdentifier: 'b'),
                    new ShorthandArrayPart('e', subarray: 'c:d, "e#":f, \'g\': "h"'),
                ]),
            ],
            'quoted keys and digits' => [
                '{"a": b, foo6: 66, -5foo: -5bar}',
                TemplateToken::array('{"a": b, foo6: 66, -5foo: -5bar}', [
                    new ShorthandArrayPart('"a"', variableIdentifier: 'b'),
                    new ShorthandArrayPart('foo6', number: '66'),
                    new ShorthandArrayPart('-5foo', variableIdentifier: '-5bar'),
                ]),
            ],
            'whitespace separated inline viewhelper arguments' => [
                '{partial="Structures" section="ValidSection"}',
                TemplateToken::array('{partial="Structures" section="ValidSection"}', [
                    new ShorthandArrayPart('partial', quotedString: '"Structures"'),
                    new ShorthandArrayPart('section', quotedString: '"ValidSection"'),
                ]),
            ],
        ];
    }

    public static function invalidArrayCases(): array
    {
        return [
            ['{"a\': b}'],
            ['{"": "bar"}'],
            ['{\'\': "bar"}'],
            ['{foo:}'],
        ];
    }

    #[DataProvider('templatesToTokenize')]
    #[Test]
    public function tokenizingFixturesReturnsTokens(string $templateName): void
    {
        $template = file_get_contents(__DIR__ . '/../Fixtures/' . $templateName . '.html');
        self::assertIsString($template);

        $subject = new TemplateLexer();
        $tokens = $subject->tokenize($template);

        self::assertNotSame([], $tokens);
        self::assertContainsOnlyInstancesOf(TemplateToken::class, $tokens);
    }

    #[DataProvider('tokenizationCases')]
    #[Test]
    public function tokenizingEdgeCasesReturnsTokens(string $templateSource): void
    {
        $subject = new TemplateLexer();
        $tokens = $subject->tokenize($templateSource);

        self::assertNotSame([], $tokens);
        self::assertContainsOnlyInstancesOf(TemplateToken::class, $tokens);
    }

    #[Test]
    public function tokenizingCreatesStructuredTokensForOuterTemplateSyntax(): void
    {
        $subject = new TemplateLexer();

        $tokens = $subject->tokenize('before<f:link.uriTo complex:attribute="Ha>llo" a="b" c=\'d\'/>mid<![CDATA[<f:notparsed>]]>after</f:link.uriTo>');

        self::assertCount(6, $tokens);
        self::assertSame(TemplateToken::TYPE_TEXT, $tokens[0]->type);
        self::assertSame('before', $tokens[0]->source);

        self::assertSame(TemplateToken::TYPE_OPEN_VIEWHELPER_TAG, $tokens[1]->type);
        self::assertSame('f', $tokens[1]->namespaceIdentifier);
        self::assertSame('link.uriTo', $tokens[1]->methodIdentifier);
        self::assertSame(' complex:attribute="Ha>llo" a="b" c=\'d\'', $tokens[1]->attributes);
        self::assertTrue($tokens[1]->selfClosing);
        self::assertEquals([
            new TagAttribute('complex:attribute', '"Ha>llo"', 'Ha>llo'),
            new TagAttribute('a', '"b"', 'b'),
            new TagAttribute('c', '\'d\'', 'd'),
        ], $tokens[1]->tagAttributes);

        self::assertSame(TemplateToken::TYPE_TEXT, $tokens[2]->type);
        self::assertSame('mid', $tokens[2]->source);

        self::assertSame(TemplateToken::TYPE_TEXT, $tokens[3]->type);
        self::assertSame('<f:notparsed>', $tokens[3]->source);
        self::assertTrue($tokens[3]->insideCdata);

        self::assertSame(TemplateToken::TYPE_TEXT, $tokens[4]->type);
        self::assertSame('after', $tokens[4]->source);

        self::assertSame(TemplateToken::TYPE_CLOSE_VIEWHELPER_TAG, $tokens[5]->type);
        self::assertSame('f', $tokens[5]->namespaceIdentifier);
        self::assertSame('link.uriTo', $tokens[5]->methodIdentifier);
    }

    #[Test]
    public function tokenizingProvidesStructuredAttributesForQuotedValues(): void
    {
        $subject = new TemplateLexer();

        $tokens = $subject->tokenize('<f:test data-foo="bar" single=\'baz\' plain="value" />');

        self::assertCount(1, $tokens);
        self::assertSame(TemplateToken::TYPE_OPEN_VIEWHELPER_TAG, $tokens[0]->type);
        self::assertEquals([
            new TagAttribute('data-foo', '"bar"', 'bar'),
            new TagAttribute('single', '\'baz\'', 'baz'),
            new TagAttribute('plain', '"value"', 'value'),
        ], $tokens[0]->tagAttributes);
    }

    #[Test]
    public function tokenizingProvidesStructuredAttributesWhenBackslashIsInsideAttributeValue(): void
    {
        $subject = new TemplateLexer();

        $tokens = $subject->tokenize('<f:test escaped="a\\b" />');

        self::assertCount(1, $tokens);
        self::assertSame(TemplateToken::TYPE_OPEN_VIEWHELPER_TAG, $tokens[0]->type);
        self::assertEquals([
            new TagAttribute('escaped', '"a\\b"', 'a\b'),
        ], $tokens[0]->tagAttributes);
    }

    #[DataProvider('shorthandCases')]
    #[Test]
    public function tokenizingShorthandSectionsReturnsExpectedTokens(string $input, array $expected): void
    {
        $subject = new TemplateLexer();

        $tokens = $subject->tokenize($input);

        self::assertSame($expected, array_map(
            static fn(TemplateToken $token): array => [$token->type, $token->source, $token->normalizedSource],
            $tokens,
        ));
    }

    #[DataProvider('objectAccessorTokenCases')]
    #[Test]
    public function tokenizingObjectAccessorsReturnsStructuredTokens(string $input, TemplateToken $expected): void
    {
        $subject = new TemplateLexer();

        $tokens = $subject->tokenize($input);

        self::assertCount(1, $tokens);
        self::assertEquals($expected, $tokens[0]);
    }

    #[DataProvider('shorthandArrayTokenCases')]
    #[Test]
    public function tokenizingShorthandArraysReturnsArrayTokens(string $input, TemplateToken $expected): void
    {
        $subject = new TemplateLexer();
        $tokens = $subject->tokenize($input);

        self::assertCount(1, $tokens);
        self::assertEquals($expected, $tokens[0]);
    }

    #[DataProvider('invalidArrayCases')]
    #[Test]
    public function tokenizingInvalidArraySyntaxFallsBackToShorthandTokens(string $input): void
    {
        $subject = new TemplateLexer();
        $tokens = $subject->tokenize($input);

        self::assertCount(1, $tokens);
        self::assertNotSame(TemplateToken::TYPE_ARRAY, $tokens[0]->type);
        self::assertSame($input, $tokens[0]->source);
    }
}
