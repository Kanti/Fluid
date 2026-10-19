<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Tests\Functional\Core\ViewHelper;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\ViewHelper\InvalidArgumentValueException;
use TYPO3Fluid\Fluid\Core\ViewHelper\MethodViewHelperAdapter;
use TYPO3Fluid\Fluid\Core\ViewHelper\UndeclaredArgumentException;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperResolver;
use TYPO3Fluid\Fluid\Tests\Functional\AbstractFunctionalTestCase;
use TYPO3Fluid\Fluid\Tests\Functional\Fixtures\MethodViewHelpers\Formatting;
use TYPO3Fluid\Fluid\View\TemplateView;

final class MethodViewHelpersTest extends AbstractFunctionalTestCase
{
    public static function renderingDataProvider(): iterable
    {
        yield 'tag, default and context' => ['<m:greet name="Ada" />', ['suffix' => '!'], 'Hello Ada!'];
        yield 'inline and named arguments' => ["{m:greet(greeting: 'Hi', name: 'Ada')}", [], 'Hi Ada'];
        yield 'multiple methods' => ['<m:greet name="Ada" /><m:format.upper value="hi" />', [], 'Hello AdaHI'];
        yield 'alias' => ['<m:format.upper value="hello" />', [], 'HELLO'];
        yield 'children' => ['<m:repeat>hi</m:repeat>', [], 'hihi'];
        yield 'explicit content wins' => ['<m:repeat value="argument">children</m:repeat>', [], 'argumentargument'];
        yield 'integer processing' => ['<m:repeat value="x" count="3" />', [], 'xxx'];
        yield 'boolean expression' => ['<m:repeat value="hi" uppercase="{number} > 1" />', ['number' => 2], 'HIHI'];
        yield 'escaped argument' => ['<m:greet name="{value}" />', ['value' => '<b>Ada</b>'], 'Hello &lt;b&gt;Ada&lt;/b&gt;'];
        yield 'escaped content' => ['<m:repeat>{value}</m:repeat>', ['value' => '<b>x</b>'], '&lt;b&gt;x&lt;/b&gt;&lt;b&gt;x&lt;/b&gt;'];
        yield 'raw output and children' => ['<m:raw><i>hi</i></m:raw>', [], '<strong><i>hi</i></strong>'];
        yield 'recursive calls' => ['<m:repeat count="1">outer<m:repeat value="inner" />end</m:repeat>', [], 'outerinnerinnerend'];
        yield 'optional request' => ['<m:request />', [], 'no request'];
        yield 'optional context and request' => ['<m:environment />', ['environment' => 'context'], 'context|no request'];
        yield 'content default' => ['<m:defaultContent>children</m:defaultContent>', [], 'fallback'];
        yield 'null content uses children' => ['<m:defaultContent value="{missing}">children</m:defaultContent>', [], 'children'];
    }

    #[Test]
    #[DataProvider('renderingDataProvider')]
    public function rendersUncachedAndCachedTemplates(string $source, array $variables, string $expected): void
    {
        for ($run = 0; $run < 2; $run++) {
            self::assertSame($expected, $this->createView($source, $variables)->render(), $run === 0 ? 'uncached' : 'cached');
        }
    }

    #[Test]
    public function requestIsResolvedForEachRenderingContext(): void
    {
        foreach (['GET', 'POST'] as $method) {
            $request = self::createStub(ServerRequestInterface::class);
            $request->method('getMethod')->willReturn($method);
            $view = $this->createView('<m:request /><m:requiredRequest /><m:environment />', ['environment' => 'context']);
            $view->getRenderingContext()->setAttribute(ServerRequestInterface::class, $request);
            self::assertSame($method . $method . 'context|' . $method, $view->render());
        }
    }

    #[Test]
    public function requiredRequestFailsClearlyWhenUnavailable(): void
    {
        self::expectException(InvalidArgumentValueException::class);
        self::expectExceptionCode(1792401008);
        $this->createView('<m:requiredRequest />')->render();
    }

    #[Test]
    public function injectedParametersCannotBePassedFromTemplates(): void
    {
        self::expectException(UndeclaredArgumentException::class);
        $this->createView('<m:request request="fake" />')->render();
    }

    #[Test]
    public function unmarkedMethodsCannotBeResolved(): void
    {
        self::expectExceptionCode(1407060572);
        $this->createView('<m:hidden />')->render();
    }

    #[Test]
    public function requiredTemplateArgumentsRemainRequired(): void
    {
        self::expectExceptionCode(1237823699);
        $this->createView('<m:greet />')->render();
    }

    #[Test]
    public function invalidTypesAreRejectedInUncachedAndCachedTemplates(): void
    {
        for ($run = 0; $run < 2; $run++) {
            try {
                $this->createView('<m:repeat count="{invalid}" />', ['invalid' => []])->render();
                self::fail('Invalid argument types must be rejected.');
            } catch (InvalidArgumentValueException $exception) {
                self::assertSame(1256475113, $exception->getCode());
            }
        }
    }

    #[Test]
    public function ordinaryObjectsCanBeProvidedByADependencyContainer(): void
    {
        for ($run = 0; $run < 2; $run++) {
            $resolver = new class () extends ViewHelperResolver {
                public function createMethodViewHelperInstance(string $className): object
                {
                    return new $className('DI: ');
                }
            };
            $view = $this->createView('<m:greet name="Ada" />', [], $resolver);
            self::assertSame('DI: Hello Ada', $view->render());
        }
    }

    #[Test]
    public function aSharedAdapterKeepsMethodAndNodeStateSeparate(): void
    {
        for ($run = 0; $run < 2; $run++) {
            $resolver = new class () extends ViewHelperResolver {
                private ?MethodViewHelperAdapter $adapter = null;

                public function createViewHelperInstanceFromClassName(string $viewHelperClassName): ViewHelperInterface
                {
                    if ($viewHelperClassName === MethodViewHelperAdapter::class) {
                        return $this->adapter ??= new MethodViewHelperAdapter();
                    }
                    return parent::createViewHelperInstanceFromClassName($viewHelperClassName);
                }
            };
            $view = $this->createView('<m:repeat count="{m:integer(value: 1)}">body<m:greet name="Ada" /></m:repeat>', [], $resolver);
            self::assertSame('bodyHello Ada', $view->render());
        }
    }

    private function createView(string $source, array $variables = [], ?ViewHelperResolver $resolver = null): TemplateView
    {
        $context = new RenderingContext();
        if ($resolver !== null) {
            $context->setViewHelperResolver($resolver);
        }
        $context->getViewHelperResolver()->addNamespace('m', Formatting::class);
        $context->setCache(self::$cache);
        $context->getTemplatePaths()->setTemplateSource($source);
        $view = new TemplateView($context);
        $view->assignMultiple($variables);
        return $view;
    }
}
