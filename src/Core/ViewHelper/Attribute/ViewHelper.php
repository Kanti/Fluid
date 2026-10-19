<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Core\ViewHelper\Attribute;

/**
 * Exposes a public instance method of an ordinary class as a ViewHelper.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class ViewHelper
{
    public function __construct(
        public ?string $name = null,
        public bool $escapeOutput = true,
        public ?bool $escapeChildren = null,
        public ?string $contentArgumentName = null,
    ) {}
}
