<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Tests\Unit\Core\ViewHelper;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Attribute\Argument;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperInvoker;
use TYPO3Fluid\Fluid\Tests\Unit\Core\Fixtures\TestViewHelper;

final class ViewHelperInvokerTest extends TestCase
{
    #[Test]
    public function legacyInterfaceOnlyViewHelpersKeepTheirRenderingLifecycle(): void
    {
        $viewHelper = $this->createMock(ViewHelperInterface::class);
        $viewHelper->expects(self::once())->method('prepareArguments')->willReturn([]);
        $viewHelper->expects(self::once())->method('initializeArgumentsAndRender')->willReturn('legacy');
        $context = new RenderingContext();
        self::assertSame('legacy', $context->getViewHelperInvoker()->invoke($viewHelper, [], $context));
    }

    #[Test]
    public function attributedAbstractViewHelpersKeepTheirCustomRenderingLifecycle(): void
    {
        $viewHelper = new class () extends AbstractViewHelper {
            public function initializeArgumentsAndRender(): mixed
            {
                return 'custom';
            }

            public function render(#[Argument('')] string $value = ''): string
            {
                return 'render';
            }
        };
        $context = new RenderingContext();
        self::assertSame('custom', $context->getViewHelperInvoker()->invoke($viewHelper, [], $context));
    }

    public static function getInvocationTestValues(): array
    {
        return [
            [TestViewHelper::class, ['param1' => 'foo', 'param2' => ['bar']], 'foo'],
            [TestViewHelper::class, ['param1' => 'foo', 'param2' => ['bar'], 'add1' => 'baz', 'add2' => 'zap'], 'foo'],
        ];
    }

    #[DataProvider('getInvocationTestValues')]
    #[Test]
    public function testInvokeViewHelper(string $viewHelperClassName, array $arguments, string $expectedOutput): void
    {
        $invoker = new ViewHelperInvoker();
        $renderingContext = new RenderingContext();
        $result = $invoker->invoke($viewHelperClassName, $arguments, $renderingContext);
        self::assertEquals($expectedOutput, $result);
    }
}
