<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Tests\Functional\Core\ViewHelper;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3Fluid\Fluid\Core\Parser\Exception;
use TYPO3Fluid\Fluid\Core\ViewHelper\InvalidArgumentValueException;
use TYPO3Fluid\Fluid\Core\ViewHelper\UndeclaredArgumentException;
use TYPO3Fluid\Fluid\Tests\Functional\AbstractFunctionalTestCase;
use TYPO3Fluid\Fluid\View\TemplateView;

final class AttributedArgumentsTest extends AbstractFunctionalTestCase
{
    public static function renderingDataProvider(): iterable
    {
        yield 'PHP parameter default' => ['<test:attributedRange start="1" end="3" />', [], '1,2,3'];
        yield 'explicit arguments out of order' => ['<test:attributedRange step="2" end="5" start="1" />', [], '1,3,5'];
        yield 'inline syntax' => ['{test:attributedRange(end: 3, start: 1)}', [], '1,2,3'];
        yield 'inherited render method' => ['<test:inheritedAttributedRange start="1" end="3" />', [], '1,2,3'];
        yield 'children and context' => ['<test:attributedContent uppercase="true">hello</test:attributedContent>', ['suffix' => '!'], 'HELLO!'];
        yield 'boolean expression' => ['<test:attributedContent value="hello" uppercase="{number} > 1" />', ['number' => 2, 'suffix' => '!'], 'HELLO!'];
        yield 'unescaped argument' => ['<test:attributedContent value="{value}" />', ['value' => '<b>hello</b>'], '&lt;b&gt;hello&lt;/b&gt;'];
        yield 'nullable default' => ['<test:attributedContent />', ['suffix' => '!'], '!'];
        yield 'recursive helper invocation' => ['<test:attributedContent uppercase="true">outer<test:attributedContent value="inner" />end</test:attributedContent>', [], 'OUTERINNEREND'];
        yield 'interface-only PHP defaults' => ['<test:interfaceAttributed />', ['suffix' => '!'], '!'];
        yield 'interface-only processing and context' => ['<test:interfaceAttributed repeat="2" uppercase="true" value="hello" />', ['suffix' => '!'], 'HELLOHELLO!'];
        yield 'interface-only boolean expression' => ['<test:interfaceAttributed uppercase="{number} > 1" value="hello" />', ['number' => 2], 'HELLO'];
        yield 'interface-only children' => ['<test:interfaceAttributed>hello</test:interfaceAttributed>', [], 'hello'];
        yield 'interface-only content argument' => ['<test:interfaceAttributed value="argument">children</test:interfaceAttributed>', [], 'argument'];
        yield 'interface-only escaping' => ['<test:interfaceAttributed value="{value}" />', ['value' => '<b>hello</b>'], '&lt;b&gt;hello&lt;/b&gt;'];
        yield 'interface-only inline syntax' => ['{test:interfaceAttributed(value: \'hello\', repeat: 2)}', [], 'hellohello'];
        yield 'nested interface-only calls' => ['<test:interfaceAttributed uppercase="true">outer<test:interfaceAttributed value="inner" />end</test:interfaceAttributed>', [], 'OUTERINNEREND'];
    }

    #[Test]
    #[DataProvider('renderingDataProvider')]
    public function rendersUncachedAndCachedTemplates(string $source, array $variables, string $expected): void
    {
        for ($run = 0; $run < 2; $run++) {
            $view = $this->createView($source, $variables);
            self::assertSame($expected, $view->render(), $run === 0 ? 'uncached' : 'cached');
        }
    }

    #[Test]
    public function missingRequiredArgumentIsRejected(): void
    {
        self::expectException(Exception::class);
        self::expectExceptionCode(1237823699);
        $this->createView('<test:attributedRange start="1" />')->render();
    }

    #[Test]
    public function invalidArgumentTypesAreRejectedInUncachedAndCachedTemplates(): void
    {
        $source = '<test:attributedRange start="{invalid}" end="3" />';
        for ($run = 0; $run < 2; $run++) {
            try {
                $this->createView($source, ['invalid' => []])->render();
                self::fail('An invalid argument type must be rejected.');
            } catch (InvalidArgumentValueException $exception) {
                self::assertSame(1256475113, $exception->getCode());
            }
        }
    }

    #[Test]
    public function renderingContextIsNotATemplateArgument(): void
    {
        self::expectException(UndeclaredArgumentException::class);
        $this->createView('<test:attributedContent context="invalid" />')->render();
    }

    #[Test]
    public function interfaceOnlyArgumentsAreValidatedInUncachedAndCachedTemplates(): void
    {
        $source = '<test:interfaceAttributed repeat="{invalid}" />';
        for ($run = 0; $run < 2; $run++) {
            try {
                $this->createView($source, ['invalid' => []])->render();
                self::fail('An invalid argument type must be rejected.');
            } catch (InvalidArgumentValueException $exception) {
                self::assertSame(1256475113, $exception->getCode());
            }
        }
    }

    #[Test]
    public function interfaceOnlyAdditionalArgumentsAreRejected(): void
    {
        self::expectException(UndeclaredArgumentException::class);
        $this->createView('<test:interfaceAttributed unexpected="invalid" />')->render();
    }

    private function createView(string $source, array $variables = []): TemplateView
    {
        $view = new TemplateView();
        $view->getRenderingContext()->getViewHelperResolver()->addNamespace('test', 'TYPO3Fluid\\Fluid\\Tests\\Functional\\Fixtures\\ViewHelpers');
        $view->getRenderingContext()->setCache(self::$cache);
        $view->getRenderingContext()->getTemplatePaths()->setTemplateSource($source);
        $view->assignMultiple($variables);
        return $view;
    }
}
