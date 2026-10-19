<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Tests\Functional\Fixtures\MethodViewHelpers;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Attribute\Argument;
use TYPO3Fluid\Fluid\Core\ViewHelper\Attribute\ViewHelper;

class Formatting
{
    public function __construct(private readonly string $prefix = '') {}

    /**
     * Greet a template user.
     *
     * @see https://example.com/greetings
     */
    #[ViewHelper]
    public function greet(
        #[Argument('Name to greet.')]
        string $name,
        #[Argument('Greeting to use.')]
        string $greeting = 'Hello',
        ?RenderingContextInterface $context = null,
    ): string {
        return $this->prefix . $greeting . ' ' . $name . $context?->getVariableProvider()->get('suffix');
    }

    #[ViewHelper(name: 'format.upper')]
    public function uppercase(#[Argument('')] string $value): string
    {
        return strtoupper($value);
    }

    #[ViewHelper(contentArgumentName: 'value')]
    public function repeat(
        #[Argument('', escape: false)]
        ?string $value = null,
        #[Argument('Number of copies.')]
        int $count = 2,
        #[Argument('Whether to uppercase the content.')]
        bool $uppercase = false,
    ): string {
        return str_repeat($uppercase ? strtoupper($value ?? '') : ($value ?? ''), $count);
    }

    #[ViewHelper(escapeOutput: false, escapeChildren: false, contentArgumentName: 'value')]
    public function raw(#[Argument('', escape: false)] ?string $value = null): string
    {
        return '<strong>' . $value . '</strong>';
    }

    #[ViewHelper]
    public function request(?ServerRequestInterface $request = null): string
    {
        return $request?->getMethod() ?? 'no request';
    }

    #[ViewHelper]
    public function requiredRequest(ServerRequestInterface $request): string
    {
        return $request->getMethod();
    }

    #[ViewHelper]
    public function environment(?RenderingContextInterface $context = null, ?ServerRequestInterface $request = null): string
    {
        return $context?->getVariableProvider()->get('environment') . '|' . ($request?->getMethod() ?? 'no request');
    }

    #[ViewHelper(contentArgumentName: 'value')]
    public function defaultContent(#[Argument('')] ?string $value = 'fallback'): string
    {
        return $value ?? '';
    }

    #[ViewHelper]
    public function integer(#[Argument('')] int $value): int
    {
        return $value;
    }

    public function hidden(): string
    {
        throw new \LogicException('Unmarked methods must never be invoked from templates.');
    }
}
