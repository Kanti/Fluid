<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Core\Parser\Lexer;

final readonly class ShorthandToken
{
    public const TYPE_TEXT = 'text';
    public const TYPE_SHORTHAND = 'shorthand';

    public function __construct(
        public string $type,
        public string $source,
        public string $normalizedSource,
    ) {}

    public static function text(string $source): self
    {
        return new self(self::TYPE_TEXT, $source, $source);
    }

    public static function shorthand(string $source, string $normalizedSource): self
    {
        return new self(self::TYPE_SHORTHAND, $source, $normalizedSource);
    }
}
