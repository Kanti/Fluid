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
    ) {}

    public static function text(string $source): self
    {
        return new self(self::TYPE_TEXT, $source);
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
        );
    }

    public static function cdata(string $source, string $content): self
    {
        return new self(self::TYPE_CDATA, $source, content: $content);
    }
}
