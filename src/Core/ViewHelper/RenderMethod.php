<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Core\ViewHelper;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Attribute\Argument;

/**
 * Reflects render parameters without constructing or initializing a ViewHelper.
 *
 * @internal
 */
final class RenderMethod
{
    /** @var array<string, self> */
    private static array $cache = [];

    /** @var array<class-string, bool> */
    private static array $usesMethodArguments = [];

    private \ReflectionMethod $method;

    /** @var array<string, ArgumentDefinition> */
    private array $argumentDefinitions = [];

    private ?string $renderingContextParameter = null;

    private ?\ReflectionParameter $requestParameter = null;

    private function __construct(string $className, string $methodName)
    {
        if (!method_exists($className, $methodName)) {
            throw new InvalidArgumentDefinitionException('ViewHelper ' . $className . ' must implement a public ' . $methodName . '() method.', 1792320001);
        }
        $method = new \ReflectionMethod($className, $methodName);
        $this->method = $method;
        if (!$method->isPublic() || $method->isStatic()) {
            throw new InvalidArgumentDefinitionException('ViewHelper ' . $className . ' must implement a public, non-static ' . $methodName . '() method.', 1792320002);
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
            if ($attributes === [] && $type instanceof \ReflectionNamedType && $type->getName() === ServerRequestInterface::class) {
                if ($parameter->isPassedByReference() || $parameter->isVariadic() || $this->requestParameter !== null) {
                    throw new InvalidArgumentDefinitionException('Only one request parameter, passed by value, is supported.', 1792401007);
                }
                $this->requestParameter = $parameter;
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
    public static function fromClass(string $className, string $methodName = 'render'): self
    {
        return self::$cache[$className . '::' . $methodName] ??= new self($className, $methodName);
    }

    /**
     * Legacy implementations may have no render() method at all.
     *
     * @param class-string $className
     */
    public static function tryFromClass(string $className): ?self
    {
        if (!array_key_exists($className, self::$usesMethodArguments)) {
            self::$usesMethodArguments[$className] = false;
            if (method_exists($className, 'render')) {
                foreach ((new \ReflectionMethod($className, 'render'))->getParameters() as $parameter) {
                    $type = $parameter->getType();
                    if ($parameter->getAttributes(Argument::class) !== []
                        || ($type instanceof \ReflectionNamedType && in_array($type->getName(), [RenderingContextInterface::class, ServerRequestInterface::class], true))
                    ) {
                        self::$usesMethodArguments[$className] = true;
                        break;
                    }
                }
            }
        }
        return self::$usesMethodArguments[$className] ? self::fromClass($className) : null;
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
        if ($this->requestParameter !== null) {
            if ($renderingContext !== null && $renderingContext->hasAttribute(ServerRequestInterface::class)) {
                $request = $renderingContext->getAttribute(ServerRequestInterface::class);
            } elseif ($this->requestParameter->isDefaultValueAvailable()) {
                $request = $this->requestParameter->getDefaultValue();
            } elseif ($this->requestParameter->allowsNull()) {
                $request = null;
            } else {
                throw new InvalidArgumentValueException('A ServerRequestInterface must be attached to the rendering context.', 1792401008);
            }
            $parameters[$this->requestParameter->getName()] = $request;
        }
        return $parameters;
    }

    /** @param array<string, mixed> $arguments */
    public function invoke(object $viewHelper, array $arguments, RenderingContextInterface $renderingContext): mixed
    {
        return $this->method->invokeArgs($viewHelper, $this->getInvocationArguments($arguments, $renderingContext));
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
