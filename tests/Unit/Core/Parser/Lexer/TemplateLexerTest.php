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
use TYPO3Fluid\Fluid\Core\Parser\Lexer\RegexTemplateLexer;
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

    #[DataProvider('templatesToTokenize')]
    #[Test]
    public function tokenizingFixturesMatchesRegexLexer(string $templateName): void
    {
        $template = file_get_contents(__DIR__ . '/../Fixtures/' . $templateName . '.html');
        self::assertIsString($template);

        $subject = new TemplateLexer();
        $regexLexer = new RegexTemplateLexer();

        self::assertEquals($regexLexer->tokenize($template), $subject->tokenize($template));
    }

    #[DataProvider('tokenizationCases')]
    #[Test]
    public function tokenizingEdgeCasesMatchesRegexLexer(string $templateSource): void
    {
        $subject = new TemplateLexer();
        $regexLexer = new RegexTemplateLexer();

        self::assertEquals($regexLexer->tokenize($templateSource), $subject->tokenize($templateSource));
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

        self::assertSame(TemplateToken::TYPE_CDATA, $tokens[3]->type);
        self::assertSame('<![CDATA[<f:notparsed>]]>', $tokens[3]->source);
        self::assertSame('<f:notparsed>', $tokens[3]->content);

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
}
