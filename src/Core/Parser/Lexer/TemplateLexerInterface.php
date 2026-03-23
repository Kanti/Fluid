<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Core\Parser\Lexer;

interface TemplateLexerInterface
{
    /**
     * @return list<TemplateToken>
     */
    public function tokenize(string $templateSource): array;
}
