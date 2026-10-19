<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Core\ViewHelper;

use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\NodeInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Class ViewHelperInvoker
 *
 * Class which is responsible for calling the render methods
 * on ViewHelpers, and this alone.
 *
 * Can be replaced via the ViewHelperResolver if the system
 * that implements Fluid requires special handling of classes.
 * This includes for example when you want to validate arguments
 * differently, wish to use another ViewHelper initialization
 * process, or wish to store instances of ViewHelpers to reuse
 * as if they were Singletons.
 *
 * To override the instantiation process and class name resolving,
 * see ViewHelperResolver. This particular class should only be
 * responsible for invoking the render method of a ViewHelper
 * using the properties available in the node.
 */
class ViewHelperInvoker
{
    /**
     * Invoke the ViewHelper described by the ViewHelperNode, the properties
     * of which will already have been filled by the ViewHelperResolver.
     */
    public function invoke(string|ViewHelperInterface $viewHelperClassNameOrInstance, array $arguments, RenderingContextInterface $renderingContext, ?\Closure $renderChildrenClosure = null): mixed
    {
        $viewHelperResolver = $renderingContext->getViewHelperResolver();
        if ($viewHelperClassNameOrInstance instanceof ViewHelperInterface) {
            $viewHelper = $viewHelperClassNameOrInstance;
        } else {
            $viewHelper = $viewHelperResolver->createViewHelperInstanceFromClassName($viewHelperClassNameOrInstance);
        }
        $argumentDefinitions = $viewHelperResolver->getArgumentDefinitionsForViewHelper($viewHelper);

        try {
            if ($viewHelper instanceof MethodViewHelperAdapter) {
                $viewHelper->setRenderingContext($renderingContext);
                $viewHelper->setArguments($arguments);
                if ($renderChildrenClosure !== null) {
                    $viewHelper->setRenderChildrenClosure($renderChildrenClosure);
                }
                return $viewHelper->initializeArgumentsAndRender();
            }
            // Convert nodes to actual values (in uncached context)
            $arguments = array_map(
                fn($value) => $value instanceof NodeInterface ? $value->evaluate($renderingContext) : $value,
                $arguments,
            );

            [$registeredArguments, $arguments] = $this->processArguments(get_class($viewHelper), $arguments, $argumentDefinitions, $renderingContext);

            if ($renderChildrenClosure) {
                $viewHelper->setRenderChildrenClosure($renderChildrenClosure);
            }
            $viewHelper->setRenderingContext($renderingContext);
            $viewHelper->setArguments($registeredArguments);
            $viewHelper->handleAdditionalArguments($arguments);
            if ($viewHelper instanceof ViewHelperArgumentsValidatedEventInterface) {
                $viewHelper::argumentsValidatedEvent(
                    $registeredArguments,
                    $argumentDefinitions,
                    $viewHelper,
                );
            }
            if (!$viewHelper instanceof AbstractViewHelper && ($renderMethod = RenderMethod::tryFromClass($viewHelper::class)) !== null) {
                return $renderMethod->invoke($viewHelper, $registeredArguments, $renderingContext);
            }
            return $viewHelper->initializeArgumentsAndRender();
        } catch (Exception $error) {
            return $this->handleError($error, $renderingContext);
        }
    }
    /**
     * Invoke a marked method without requiring a ViewHelper base class or interface.
     *
     * @param class-string $className
     * @param array<string, mixed> $arguments
     */
    public function invokeMethod(string $className, string $methodName, array $arguments, RenderingContextInterface $renderingContext, ?\Closure $renderChildrenClosure = null): mixed
    {
        try {
            $resolver = $renderingContext->getViewHelperResolver();
            $collection = $resolver->getResolverDelegate($className);
            if (!$collection instanceof MethodViewHelperCollection) {
                throw new InvalidArgumentDefinitionException('A marked method collection is required: ' . $className, 1792401009);
            }
            $name = array_search($methodName, $collection->getMethods(), true);
            if ($name === false) {
                throw new UnresolvableViewHelperException('The method is not marked as a ViewHelper: ' . $className . '::' . $methodName, 1792401010);
            }
            $method = RenderMethod::fromClass($className, $methodName);
            $definitions = $method->getArgumentDefinitions();
            $arguments = array_map(
                fn($value) => $value instanceof NodeInterface ? $value->evaluate($renderingContext) : $value,
                $arguments,
            );
            $contentArgumentName = $collection->getAttribute($name)->contentArgumentName;
            if ($contentArgumentName !== null && !array_key_exists($contentArgumentName, $arguments)) {
                $arguments[$contentArgumentName] = $definitions[$contentArgumentName]->getDefaultValue();
            }
            if ($contentArgumentName !== null && !isset($arguments[$contentArgumentName]) && $renderChildrenClosure !== null) {
                $arguments[$contentArgumentName] = $renderChildrenClosure();
            }
            foreach ($definitions as $argumentName => $definition) {
                if ($definition->isRequired() && !array_key_exists($argumentName, $arguments)) {
                    throw new MissingArgumentException('Required argument "' . $argumentName . '" was not supplied.', 1792401011);
                }
            }
            [$registeredArguments, $additionalArguments] = $this->processArguments($className . '::' . $methodName, $arguments, $definitions, $renderingContext);
            if ($additionalArguments !== []) {
                throw new UndeclaredArgumentException('Undeclared method ViewHelper arguments: ' . implode(', ', array_keys($additionalArguments)), 1792401012);
            }
            return $method->invoke($resolver->createMethodViewHelperInstance($className), $registeredArguments, $renderingContext);
        } catch (Exception $error) {
            return $this->handleError($error, $renderingContext);
        }
    }

    /**
     * @param array<string, mixed> $arguments
     * @param array<string, ArgumentDefinition> $argumentDefinitions
     * @return array{array<string, mixed>, array<string, mixed>}
     */
    private function processArguments(string $viewHelperName, array $arguments, array $argumentDefinitions, RenderingContextInterface $renderingContext): array
    {
        // Determine arguments defined by the ViewHelper API
        $registeredArguments = [];
        foreach ($argumentDefinitions as $argumentName => $argumentDefinition) {
            if (isset($arguments[$argumentName])) {
                // Perform argument processing and validation
                $value = $renderingContext->getArgumentProcessor()->process($arguments[$argumentName], $argumentDefinition);
                if (!$renderingContext->getArgumentProcessor()->isValid($value, $argumentDefinition)) {
                    $givenType = is_object($value) ? get_class($value) : gettype($value);
                    throw new InvalidArgumentValueException(sprintf(
                        'The argument "%s" was registered with type "%s", but is of type "%s" in view helper "%s".',
                        $argumentName,
                        $argumentDefinition->getType(),
                        $givenType,
                        $viewHelperName,
                    ), 1256475113);
                }
                $registeredArguments[$argumentName] = $value;
            } else {
                // @todo we might add a check for isRequired() here. Currently, this relies on the check
                //       being performed by the TemplateParser
                $registeredArguments[$argumentName] = $argumentDefinition->getDefaultValue();
            }

            // Argument has definition, so it is no additionalArgument
            unset($arguments[$argumentName]);
        }

        return [$registeredArguments, $arguments];
    }

    private function handleError(Exception $error, RenderingContextInterface $renderingContext): mixed
    {
        if (method_exists($renderingContext, 'getOriginalTemplatePath')) {
            return $renderingContext->getErrorHandler()->handleViewHelperError($error, $renderingContext->getOriginalTemplatePath());
        }
        return $renderingContext->getErrorHandler()->handleViewHelperError($error);
    }

}
