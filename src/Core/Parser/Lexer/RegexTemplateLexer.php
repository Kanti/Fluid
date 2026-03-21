<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Core\Parser\Lexer;

use TYPO3Fluid\Fluid\Core\Parser\Patterns;

final class RegexTemplateLexer implements TemplateLexerInterface
{
    public function tokenize(string $templateSource): array
    {
        $parts = preg_split(
            Patterns::$SPLIT_PATTERN_TEMPLATE_DYNAMICTAGS,
            $templateSource,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
        );
        if (!is_array($parts)) {
            return [TemplateToken::text($templateSource)];
        }

        $tokens = [];
        foreach ($parts as $part) {
            $matchedVariables = [];
            if (preg_match(Patterns::$SCAN_PATTERN_TEMPLATE_VIEWHELPERTAG, $part, $matchedVariables) > 0) {
                $tokens[] = TemplateToken::openViewHelperTag(
                    $part,
                    $matchedVariables['NamespaceIdentifier'],
                    $matchedVariables['MethodIdentifier'],
                    $matchedVariables['Attributes'],
                    $matchedVariables['Selfclosing'] !== '',
                );
                continue;
            }
            if (preg_match(Patterns::$SCAN_PATTERN_TEMPLATE_CLOSINGVIEWHELPERTAG, $part, $matchedVariables) > 0) {
                $tokens[] = TemplateToken::closeViewHelperTag(
                    $part,
                    $matchedVariables['NamespaceIdentifier'],
                    $matchedVariables['MethodIdentifier'],
                );
                continue;
            }
            if (preg_match(Patterns::$SCAN_PATTERN_CDATA, $part, $matchedVariables) > 0) {
                $tokens[] = TemplateToken::cdata($part, $matchedVariables['CDataContent']);
                continue;
            }
            $tokens[] = TemplateToken::text($part);
        }

        return $tokens;
    }
}
