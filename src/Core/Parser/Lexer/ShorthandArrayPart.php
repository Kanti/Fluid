<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Core\Parser\Lexer;

final readonly class ShorthandArrayPart
{
    public function __construct(
        public string $key,
        public ?string $quotedString = null,
        public ?string $variableIdentifier = null,
        public ?string $number = null,
        public ?string $subarray = null,
        public ?string $expressionValue = null,
    ) {}
}
