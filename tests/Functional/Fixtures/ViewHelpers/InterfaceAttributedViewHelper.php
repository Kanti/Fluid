<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Tests\Functional\Fixtures\ViewHelpers;

use TYPO3Fluid\Fluid\Core\Compiler\TemplateCompiler;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\NodeInterface;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Attribute\Argument;
use TYPO3Fluid\Fluid\Core\ViewHelper\UndeclaredArgumentException;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperInterface;

class InterfaceAttributedViewHelper implements ViewHelperInterface
{
    private ViewHelperNode $node;

    private RenderingContextInterface $context;

    private array $arguments = [];

    private ?\Closure $renderChildrenClosure = null;

    public function prepareArguments(): array
    {
        throw new \LogicException('Attributed definitions must be discovered without calling prepareArguments().');
    }

    public function setArguments(array $arguments): void
    {
        $this->arguments = $arguments;
    }

    public function getContentArgumentName(): string
    {
        return 'value';
    }

    public function setViewHelperNode(ViewHelperNode $node): void
    {
        $this->node = $node;
    }

    public function setRenderingContext(RenderingContextInterface $renderingContext): void
    {
        $this->context = $renderingContext;
    }

    public function initializeArgumentsAndRender(): mixed
    {
        throw new \LogicException('Attributed interface-only ViewHelpers must be invoked through render().');
    }

    public function handleAdditionalArguments(array $arguments): void {}

    public function validateAdditionalArguments(array $arguments): void
    {
        if ($arguments !== []) {
            throw new UndeclaredArgumentException('Unexpected arguments: ' . implode(', ', array_keys($arguments)), 1792400100);
        }
    }

    public function setRenderChildrenClosure(\Closure $renderChildrenClosure): void
    {
        $this->renderChildrenClosure = $renderChildrenClosure;
    }

    public function convert(TemplateCompiler $templateCompiler): array
    {
        $children = $templateCompiler->variableName('children');
        $initialization = $children . ' = ' . $templateCompiler->wrapChildNodesInClosure($this->node) . ';' . chr(10);
        $arguments = [];
        foreach ($this->node->getArguments() as $name => $value) {
            if ($value instanceof NodeInterface) {
                $converted = $value->convert($templateCompiler);
                $initialization .= $converted['initialization'];
                $arguments[] = var_export($name, true) . ' => ' . $converted['execution'];
            } else {
                $arguments[] = var_export($name, true) . ' => ' . var_export($value, true);
            }
        }
        return [
            'initialization' => $initialization,
            'execution' => sprintf(
                '$renderingContext->getViewHelperInvoker()->invoke(%s::class, [%s], $renderingContext, %s)',
                static::class,
                implode(', ', $arguments),
                $children,
            ),
        ];
    }

    public function isChildrenEscapingEnabled(): bool
    {
        return false;
    }

    public function isOutputEscapingEnabled(): bool
    {
        return true;
    }

    public function render(
        RenderingContextInterface $context,
        #[Argument('', escape: false)]
        ?string $value = null,
        #[Argument('Whether to uppercase the content.')]
        bool $uppercase = false,
        #[Argument('Number of copies.')]
        int $repeat = 1,
    ): string {
        if ($context !== $this->context || $repeat !== $this->arguments['repeat'] || $uppercase !== $this->arguments['uppercase']) {
            throw new \LogicException('Rendering context and argument setters must be called before render().');
        }
        $value ??= ($this->renderChildrenClosure !== null
            ? ($this->renderChildrenClosure)()
            : $this->node->evaluateChildNodes($context)) ?? '';
        return str_repeat($uppercase ? strtoupper($value) : $value, $repeat) . $context->getVariableProvider()->get('suffix');
    }
}
