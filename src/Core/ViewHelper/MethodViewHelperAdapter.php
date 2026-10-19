<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Core\ViewHelper;

use TYPO3Fluid\Fluid\Core\Compiler\TemplateCompiler;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;
use TYPO3Fluid\Fluid\Core\ViewHelper\Attribute\ViewHelper;

/** @internal */
final class MethodViewHelperAdapter extends AbstractViewHelper
{
    /** @var class-string */
    private string $className;

    private string $methodName;

    private ViewHelper $attribute;

    public function setViewHelperNode(ViewHelperNode $node): void
    {
        parent::setViewHelperNode($node);
        $collection = $node->getResolverDelegate();
        if (!$collection instanceof MethodViewHelperCollection) {
            throw new InvalidArgumentDefinitionException('Method ViewHelpers require a MethodViewHelperCollection.', 1792401006);
        }
        $this->className = $collection->getNamespace();
        $this->methodName = $collection->getMethodName($node->getName());
        $this->attribute = $collection->getAttribute($node->getName());
    }

    public function prepareArguments(): array
    {
        return RenderMethod::fromClass($this->className, $this->methodName)->getArgumentDefinitions();
    }

    public function getContentArgumentName(): ?string
    {
        return $this->attribute->contentArgumentName;
    }

    public function isChildrenEscapingEnabled(): bool
    {
        return $this->attribute->escapeChildren ?? !$this->attribute->escapeOutput;
    }

    public function isOutputEscapingEnabled(): bool
    {
        return $this->attribute->escapeOutput;
    }

    public function render(): mixed
    {
        $context = $this->renderingContext;
        $node = $this->viewHelperNode;
        $children = $this->renderChildrenClosure ?? static fn() => $node->evaluateChildNodes($context);
        return $context->getViewHelperInvoker()->invokeMethod($this->className, $this->methodName, $this->arguments, $context, $children);
    }

    public function compile(string $argumentsName, string $closureName, string &$initializationPhpCode, ViewHelperNode $node, TemplateCompiler $compiler): string
    {
        return sprintf(
            '$renderingContext->getViewHelperInvoker()->invokeMethod(%s, %s, %s, $renderingContext, %s)',
            var_export($this->className, true),
            var_export($this->methodName, true),
            $argumentsName,
            $closureName,
        );
    }

    public function convert(TemplateCompiler $templateCompiler): array
    {
        // A container may share one adapter across methods. Nested compilation
        // must not overwrite the outer node's class, method or child nodes.
        return (clone $this)->convertNode($templateCompiler);
    }

    private function convertNode(TemplateCompiler $templateCompiler): array
    {
        return parent::convert($templateCompiler);
    }
}
