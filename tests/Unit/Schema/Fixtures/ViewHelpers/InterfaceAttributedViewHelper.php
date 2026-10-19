<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Tests\Unit\Schema\Fixtures\ViewHelpers;

use TYPO3Fluid\Fluid\Tests\Functional\Fixtures\ViewHelpers\InterfaceAttributedViewHelper as FunctionalViewHelper;

final class InterfaceAttributedViewHelper extends FunctionalViewHelper
{
    public function __construct()
    {
        throw new \LogicException('The ViewHelper constructor must not run during metadata extraction.');
    }
}
