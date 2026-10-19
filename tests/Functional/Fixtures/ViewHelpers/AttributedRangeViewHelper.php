<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Tests\Functional\Fixtures\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Attribute\Argument;

class AttributedRangeViewHelper extends AbstractViewHelper
{
    public function render(
        #[Argument(description: 'First value of the sequence.')]
        int $start,
        #[Argument(description: 'Last possible value of the sequence.')]
        int $end,
        #[Argument(description: 'Step size.')]
        int $step = 1,
    ): string {
        return implode(',', range($start, $end, $step));
    }
}
