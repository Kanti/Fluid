<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Tests\Functional\Fixtures\ViewHelpers;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Attribute\Argument;

final class AttributedContentViewHelper extends AbstractViewHelper
{
    public function getContentArgumentName(): string
    {
        return 'value';
    }

    public function render(
        RenderingContextInterface $context,
        #[Argument('', escape: false)]
        ?string $value = null,
        #[Argument('')]
        bool $uppercase = false,
    ): string {
        $value ??= $this->renderChildren() ?? '';
        return ($uppercase ? strtoupper($value) : $value) . $context->getVariableProvider()->get('suffix');
    }
}
