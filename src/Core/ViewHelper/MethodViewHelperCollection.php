<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Core\ViewHelper;

use TYPO3Fluid\Fluid\Core\ViewHelper\Attribute\ViewHelper;

/**
 * Registers the marked methods of a class as a Fluid namespace.
 *
 * @api
 */
final class MethodViewHelperCollection implements ViewHelperResolverDelegateInterface
{
    /** @var array<string, string> */
    private array $methods = [];

    /** @var array<string, ViewHelper> */
    private array $attributes = [];

    /** @param class-string $className */
    public function __construct(private readonly string $className)
    {
        $class = new \ReflectionClass($className);
        if ($class->isAbstract() || $class->isInterface() || $class->isTrait()) {
            throw new InvalidArgumentDefinitionException('Method ViewHelpers need a concrete class: ' . $className, 1792401001);
        }
        foreach ($class->getMethods() as $method) {
            $attributes = $method->getAttributes(ViewHelper::class);
            if ($attributes === []) {
                continue;
            }
            if (count($attributes) !== 1) {
                throw new InvalidArgumentDefinitionException('A method must have exactly one ViewHelper attribute.', 1792401002);
            }
            $attribute = new ViewHelper(...$attributes[0]->getArguments());
            $name = $attribute->name ?? $method->getName();
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_.]*$/D', $name) || isset($this->methods[$name])) {
                throw new InvalidArgumentDefinitionException('Invalid or duplicate method ViewHelper name: ' . $name, 1792401003);
            }
            $definitions = RenderMethod::fromClass($className, $method->getName())->getArgumentDefinitions();
            if ($attribute->contentArgumentName !== null && !isset($definitions[$attribute->contentArgumentName])) {
                throw new InvalidArgumentDefinitionException('The content argument must be declared with an Argument attribute: ' . $attribute->contentArgumentName, 1792401004);
            }
            $this->methods[$name] = $method->getName();
            $this->attributes[$name] = $attribute;
        }
    }

    public function resolveViewHelperClassName(string $name): string
    {
        $this->getMethodName($name);
        return MethodViewHelperAdapter::class;
    }

    /** @return class-string */
    public function getNamespace(): string
    {
        return $this->className;
    }

    /** @return array<string, string> */
    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getMethodName(string $name): string
    {
        if (!isset($this->methods[$name])) {
            throw new UnresolvableViewHelperException('No marked ViewHelper method named "' . $name . '" in ' . $this->className, 1792401005);
        }
        return $this->methods[$name];
    }

    public function getAttribute(string $name): ViewHelper
    {
        $this->getMethodName($name);
        return $this->attributes[$name];
    }
}
