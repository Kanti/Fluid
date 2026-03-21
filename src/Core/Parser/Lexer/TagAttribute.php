<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Core\Parser\Lexer;

final readonly class TagAttribute
{
    public function __construct(
        public string $name,
        public string $quotedValue,
        public string $value,
    ) {}
}
