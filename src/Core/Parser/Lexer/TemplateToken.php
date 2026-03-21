<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Core\Parser\Lexer;

final readonly class TemplateToken
{
    public const TYPE_TEXT = 'text';
    public const TYPE_OPEN_VIEWHELPER_TAG = 'open_viewhelper_tag';
    public const TYPE_CLOSE_VIEWHELPER_TAG = 'close_viewhelper_tag';
    public const TYPE_CDATA = 'cdata';
    public const TYPE_SHORTHAND = 'shorthand';
    public const TYPE_ARRAY = 'array';
    public const TYPE_OBJECT_ACCESSOR = 'object_accessor';

    public function __construct(
        public string $type,
        public string $source,
        public ?string $namespaceIdentifier = null,
        public ?string $methodIdentifier = null,
        public ?string $attributes = null,
        /**
         * @var list<TagAttribute>
         */
        public array $tagAttributes = [],
        public bool $selfClosing = false,
        public ?string $content = null,
        public ?string $normalizedSource = null,
        public bool $insideCdata = false,
        /**
         * @var list<ShorthandArrayPart>
         */
        public array $arrayParts = [],
        public ?string $objectAccessor = null,
        /**
         * @var list<ShorthandInlineViewHelper>
         */
        public array $inlineViewHelpers = [],
    ) {}

    public static function text(string $source, bool $insideCdata = false): self
    {
        return new self(self::TYPE_TEXT, $source, normalizedSource: $source, insideCdata: $insideCdata);
    }

    public static function openViewHelperTag(
        string $source,
        string $namespaceIdentifier,
        string $methodIdentifier,
        string $attributes,
        array $tagAttributes,
        bool $selfClosing,
    ): self {
        return new self(
            self::TYPE_OPEN_VIEWHELPER_TAG,
            $source,
            $namespaceIdentifier,
            $methodIdentifier,
            $attributes,
            $tagAttributes,
            $selfClosing,
            normalizedSource: $source,
        );
    }

    public static function closeViewHelperTag(
        string $source,
        string $namespaceIdentifier,
        string $methodIdentifier,
    ): self {
        return new self(
            self::TYPE_CLOSE_VIEWHELPER_TAG,
            $source,
            $namespaceIdentifier,
            $methodIdentifier,
            normalizedSource: $source,
        );
    }

    public static function cdata(string $source, string $content): self
    {
        return new self(self::TYPE_CDATA, $source, content: $content, normalizedSource: $source);
    }

    public static function shorthand(string $source, string $normalizedSource, bool $insideCdata = false): self
    {
        return new self(self::TYPE_SHORTHAND, $source, normalizedSource: $normalizedSource, insideCdata: $insideCdata);
    }

    /**
     * @param list<ShorthandArrayPart> $arrayParts
     */
    public static function array(string $source, array $arrayParts): self
    {
        return new self(self::TYPE_ARRAY, $source, normalizedSource: $source, arrayParts: $arrayParts);
    }

    /**
     * @param list<ShorthandInlineViewHelper> $inlineViewHelpers
     */
    public static function objectAccessor(
        string $source,
        string $normalizedSource,
        string $objectAccessor,
        array $inlineViewHelpers,
        bool $insideCdata = false,
    ): self {
        return new self(
            self::TYPE_OBJECT_ACCESSOR,
            $source,
            normalizedSource: $normalizedSource,
            insideCdata: $insideCdata,
            objectAccessor: $objectAccessor,
            inlineViewHelpers: $inlineViewHelpers,
        );
    }
}
