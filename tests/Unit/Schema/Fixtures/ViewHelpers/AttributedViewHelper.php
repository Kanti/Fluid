<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Tests\Unit\Schema\Fixtures\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Attribute\Argument;

final class AttributedViewHelper extends AbstractViewHelper
{
    public function __construct()
    {
        throw new \LogicException('The ViewHelper constructor must not run during metadata extraction.');
    }

    public function prepareArguments(): array
    {
        throw new \LogicException('Custom argument preparation must not run during metadata extraction.');
    }

    public function initializeArguments(): void
    {
        throw new \LogicException('Argument initialization must not run during metadata extraction.');
    }

    public function render(#[Argument('A test argument', optional: true)] ?string $value): string
    {
        throw new \LogicException('The render method must not run during metadata extraction.');
    }
}
