<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Tests\Functional\ViewHelpers\Array;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3Fluid\Fluid\Core\ViewHelper\InvalidArgumentValueException;
use TYPO3Fluid\Fluid\Tests\Functional\AbstractFunctionalTestCase;
use TYPO3Fluid\Fluid\Tests\Functional\Fixtures\Various\ArrayAccessExample;
use TYPO3Fluid\Fluid\Tests\Functional\Fixtures\Various\IterableExample;
use TYPO3Fluid\Fluid\View\TemplateView;

final class SumViewHelperTest extends AbstractFunctionalTestCase
{
    public static function renderValidDataProvider(): \Generator
    {
        yield 'empty array' => [
            'arguments' => [
                'value' => [],
            ],
            'src' => '<f:array.sum value="{value}" />',
            'expectation' => 0,
        ];
        yield 'empty array inline' => [
            'arguments' => [
                'value' => [],
            ],
            'src' => '{value -> f:array.sum()}',
            'expectation' => 0,
        ];
        yield 'integers' => [
            'arguments' => [
                'value' => [1, 2, 3],
            ],
            'src' => '<f:array.sum value="{value}" />',
            'expectation' => 6,
        ];
        yield 'integers inline' => [
            'arguments' => [
                'value' => [1, 2, 3],
            ],
            'src' => '{value -> f:array.sum()}',
            'expectation' => 6,
        ];
        yield 'floats' => [
            'arguments' => [
                'value' => [1.5, 2.25],
            ],
            'src' => '<f:array.sum value="{value}" />',
            'expectation' => 3.75,
        ];
        yield 'negative numbers' => [
            'arguments' => [
                'value' => [5, -3, -0.5],
            ],
            'src' => '<f:array.sum value="{value}" />',
            'expectation' => 1.5,
        ];
        yield 'numeric strings' => [
            'arguments' => [
                'value' => ['1', '2.5', '-0.5'],
            ],
            'src' => '<f:array.sum value="{value}" />',
            'expectation' => 3.0,
        ];
        yield 'mixed numeric types' => [
            'arguments' => [
                'value' => [10, 2.5, '7'],
            ],
            'src' => '<f:array.sum value="{value}" />',
            'expectation' => 19.5,
        ];
        yield 'associative array' => [
            'arguments' => [
                'value' => ['a' => 1, 'b' => 2],
            ],
            'src' => '<f:array.sum value="{value}" />',
            'expectation' => 3,
        ];
        yield 'array with inline array' => [
            'arguments' => [],
            'src' => '<f:array.sum value="{0: 1, 1: 2, 2: 3}" />',
            'expectation' => 6,
        ];
        yield 'value as tag content' => [
            'arguments' => [
                'value' => [1, 2, 3],
            ],
            'src' => '<f:array.sum>{value}</f:array.sum>',
            'expectation' => 6,
        ];
        yield 'value inline as iterable' => [
            'arguments' => [
                'value' => new IterableExample([1, 2, 3]),
            ],
            'src' => '{value -> f:array.sum()}',
            'expectation' => 6,
        ];
        yield 'value inline and argument' => [
            'arguments' => [
                'valueInline' => [1, 2, 3],
                'valueArgument' => [4, 5, 6],
            ],
            'src' => '{valueInline -> f:array.sum(value: valueArgument)}',
            'expectation' => 15,
        ];
        yield 'used in text' => [
            'arguments' => [
                'value' => [10, 2.5, '7'],
            ],
            'src' => 'Total: {value -> f:array.sum()}',
            'expectation' => 'Total: 19.5',
        ];
    }

    #[DataProvider('renderValidDataProvider')]
    #[Test]
    public function renderValid(array $arguments, string $src, int|float|string $expectation): void
    {
        $view = new TemplateView();
        $view->getRenderingContext()->setCache(self::$cache);
        $view->getRenderingContext()->getTemplatePaths()->setTemplateSource($src);
        $view->assignMultiple($arguments);
        self::assertSame($expectation, $view->render());

        $view = new TemplateView();
        $view->getRenderingContext()->setCache(self::$cache);
        $view->getRenderingContext()->getTemplatePaths()->setTemplateSource($src);
        $view->assignMultiple($arguments);
        self::assertSame($expectation, $view->render());
    }

    public static function renderInvalidDataProvider(): \Generator
    {
        yield 'invalid string content' => [
            'arguments' => [],
            'src' => '<f:array.sum>SOME TEXT</f:array.sum>',
            'exceptionCode' => 1792005140,
        ];
        yield 'invalid string attribute' => [
            'arguments' => [],
            'src' => '<f:array.sum value="string" />',
            'exceptionCode' => 1256475113,
        ];
        yield 'invalid string inline' => [
            'arguments' => [
                'value' => 'string',
            ],
            'src' => '{value -> f:array.sum()}',
            'exceptionCode' => 1792005140,
        ];
        yield 'invalid integer attribute' => [
            'arguments' => [
                'value' => 123,
            ],
            'src' => '<f:array.sum value="{value}" />',
            'exceptionCode' => 1256475113,
        ];
        yield 'undefined value' => [
            'arguments' => [],
            'src' => '<f:array.sum value="{undefinedVariable}" />',
            'exceptionCode' => 1792005140,
        ];
        yield 'arrayaccess inline' => [
            'arguments' => [
                'value' => new ArrayAccessExample(['foo' => 1]),
            ],
            'src' => '{value -> f:array.sum()}',
            'exceptionCode' => 1792005140,
        ];
        yield 'non-numeric string value' => [
            'arguments' => [
                'value' => [1, 'abc'],
            ],
            'src' => '<f:array.sum value="{value}" />',
            'exceptionCode' => 1792005141,
        ];
        yield 'null value' => [
            'arguments' => [
                'value' => [1, null],
            ],
            'src' => '<f:array.sum value="{value}" />',
            'exceptionCode' => 1792005141,
        ];
        yield 'boolean value' => [
            'arguments' => [
                'value' => [1, true],
            ],
            'src' => '<f:array.sum value="{value}" />',
            'exceptionCode' => 1792005141,
        ];
        yield 'nested array value' => [
            'arguments' => [
                'value' => [1, [2, 3]],
            ],
            'src' => '<f:array.sum value="{value}" />',
            'exceptionCode' => 1792005141,
        ];
    }

    #[DataProvider('renderInvalidDataProvider')]
    #[Test]
    public function renderInvalid(array $arguments, string $src, int $exceptionCode): void
    {
        $this->expectException(InvalidArgumentValueException::class);
        $this->expectExceptionCode($exceptionCode);

        $view = new TemplateView();
        $view->getRenderingContext()->setCache(self::$cache);
        $view->getRenderingContext()->getTemplatePaths()->setTemplateSource($src);
        $view->assignMultiple($arguments);
        $view->render();

        $view = new TemplateView();
        $view->getRenderingContext()->setCache(self::$cache);
        $view->getRenderingContext()->getTemplatePaths()->setTemplateSource($src);
        $view->assignMultiple($arguments);
        $view->render();
    }
}
