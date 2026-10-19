<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Core\ViewHelper\Attribute;

/**
 * Declares a template argument on a ViewHelper's render() parameter.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final readonly class Argument
{
    public function __construct(
        public string $description = '',
        public ?bool $escape = null,
    ) {}
}
