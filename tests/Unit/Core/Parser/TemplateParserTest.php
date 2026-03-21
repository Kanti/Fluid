<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Tests\Unit\Core\Parser;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3Fluid\Fluid\Core\Parser\Configuration;
use TYPO3Fluid\Fluid\Core\Parser\ParsingState;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\RootNode;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\Variables\VariableProviderInterface;
use TYPO3Fluid\Fluid\Core\Parser\TemplateParser;

final class TemplateParserTest extends TestCase
{
    public static function templatesToSplit()
    {
        return [
            ['TemplateParserTestFixture01-shorthand'],
            ['TemplateParserTestFixture06'],
            ['TemplateParserTestFixture14'],
        ];
    }

    #[DataProvider('templatesToSplit')]
    #[Test]
    public function splitTemplateAtDynamicTagsReturnsCorrectlySplitTemplate(string $templateName): void
    {
        $template = file_get_contents(__DIR__ . '/Fixtures/' . $templateName . '.html');
        $expectedResult = require __DIR__ . '/Fixtures/' . $templateName . '-split.php';
        $subject = new TemplateParser();
        $method = new \ReflectionMethod($subject, 'splitTemplateAtDynamicTags');
        self::assertSame($expectedResult, $method->invoke($subject, $template));
    }

    #[Test]
    public function textAndShorthandSyntaxHandlerSplitsMalformedShorthandAsText(): void
    {
        $subject = new TemplateParser();
        $method = new \ReflectionMethod($subject, 'textAndShorthandSyntaxHandler');
        $method->setAccessible(true);

        $renderingContext = $this->createStub(RenderingContextInterface::class);
        $renderingContext->method('buildParserConfiguration')->willReturn(new Configuration());
        $renderingContext->method('getExpressionNodeTypes')->willReturn([]);
        $subject->setRenderingContext($renderingContext);

        $rootNode = new RootNode();
        $state = new ParsingState();
        $state->setRootNode($rootNode);
        $state->pushNodeToStack($rootNode);
        $state->setVariableProvider($this->createStub(VariableProviderInterface::class));

        $method->invoke($subject, $state, 'abc {f:if(condition: value) def', TemplateParser::CONTEXT_OUTSIDE_VIEWHELPER_ARGUMENTS);

        $children = $rootNode->getChildNodes();
        self::assertCount(1, $children);
        self::assertSame('abc {f:if(condition: value) def', $children[0]->evaluate($renderingContext));
    }

    #[Test]
    public function parseUsesTokenLineAndCharacterInErrorMessages(): void
    {
        $renderingContext = new RenderingContext();
        $subject = $renderingContext->getTemplateParser();

        self::expectExceptionMessage('line 2 at character 3');
        self::expectExceptionMessage('Unknown Namespace: foo');

        $subject->parse("\n  <foo:bar />", 'TestTemplate');
    }

}
