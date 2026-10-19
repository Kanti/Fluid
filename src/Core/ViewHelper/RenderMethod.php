<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Core\ViewHelper;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Attribute\Argument;

/**
 * Reflects render parameters without constructing or initializing a ViewHelper.
 *
 * @internal
 */
final class RenderMethod
{
    /** @var array<class-string, self> */
    private static array $cache = [];

    /** @var array<string, ArgumentDefinition> */
    private array $argumentDefinitions = [];

    private ?string $renderingContextParameter = null;

    private function __construct(string $className)
    {
        if (!method_exists($className, 'render')) {
            throw new InvalidArgumentDefinitionException('ViewHelper ' . $className . ' must implement a public render() method.', 1792320001);
        }
        $method = new \ReflectionMethod($className, 'render');
        if (!$method->isPublic() || $method->isStatic()) {
            throw new InvalidArgumentDefinitionException('ViewHelper ' . $className . ' must implement a public, non-static render() method.', 1792320002);
        }
        foreach ($method->getParameters() as $parameter) {
            $attributes = $parameter->getAttributes(Argument::class);
            $type = $parameter->getType();
            if ($attributes === [] && $type instanceof \ReflectionNamedType && $type->getName() === RenderingContextInterface::class) {
                if ($parameter->isPassedByReference() || $parameter->isVariadic() || $this->renderingContextParameter !== null) {
                    throw new InvalidArgumentDefinitionException('Only one rendering context parameter, passed by value, is supported in ' . $className . '::render().', 1792320003);
                }
                $this->renderingContextParameter = $parameter->getName();
                continue;
            }
            if ($attributes === []) {
                if (!$parameter->isOptional()) {
                    throw new InvalidArgumentDefinitionException('Render parameter "' . $parameter->getName() . '" in ' . $className . ' must declare an #[Argument] attribute.', 1792320004);
                }
                continue;
            }
            if (count($attributes) !== 1 || $parameter->isPassedByReference() || $parameter->isVariadic()) {
                throw new InvalidArgumentDefinitionException('Argument "' . $parameter->getName() . '" in ' . $className . ' must be passed by value, cannot be variadic and must have exactly one #[Argument] attribute.', 1792320005);
            }
            $argument = new Argument(...$attributes[0]->getArguments());
            $this->argumentDefinitions[$parameter->getName()] = new ArgumentDefinition(
                $parameter->getName(),
                $this->getTypeName($type, $method),
                $argument->description,
                !$parameter->isOptional(),
                $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
                $argument->escape,
            );
        }
    }

    /** @param class-string $className */
    public static function fromClass(string $className): self
    {
        return self::$cache[$className] ??= new self($className);
    }

    /** @return array<string, ArgumentDefinition> */
    public function getArgumentDefinitions(): array
    {
        return $this->argumentDefinitions;
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function getInvocationArguments(array $arguments, ?RenderingContextInterface $renderingContext): array
    {
        $parameters = [];
        foreach ($this->argumentDefinitions as $name => $definition) {
            $parameters[$name] = array_key_exists($name, $arguments) ? $arguments[$name] : $definition->getDefaultValue();
        }
        if ($this->renderingContextParameter !== null) {
            $parameters[$this->renderingContextParameter] = $renderingContext;
        }
        return $parameters;
    }

    private function getTypeName(?\ReflectionType $type, \ReflectionMethod $method): string
    {
        if ($type === null) {
            return 'mixed';
        }
        if ($type instanceof \ReflectionUnionType) {
            return implode('|', array_map(fn(\ReflectionType $part) => $this->getTypeName($part, $method), $type->getTypes()));
        }
        if (!$type instanceof \ReflectionNamedType) {
            throw new InvalidArgumentDefinitionException('Intersection types are not supported for ViewHelper arguments in ' . $method->getDeclaringClass()->getName() . '.', 1792320008);
        }
        return match ($type->getName()) {
            'self' => $method->getDeclaringClass()->getName(),
            'parent' => $method->getDeclaringClass()->getParentClass()->getName(),
            default => $type->getName(),
        };
    }
}
