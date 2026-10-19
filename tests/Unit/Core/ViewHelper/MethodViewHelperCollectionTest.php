<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Tests\Unit\Core\ViewHelper;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Attribute\Argument;
use TYPO3Fluid\Fluid\Core\ViewHelper\Attribute\ViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\InvalidArgumentDefinitionException;
use TYPO3Fluid\Fluid\Core\ViewHelper\MethodViewHelperAdapter;
use TYPO3Fluid\Fluid\Core\ViewHelper\MethodViewHelperCollection;
use TYPO3Fluid\Fluid\Core\ViewHelper\MissingArgumentException;
use TYPO3Fluid\Fluid\Core\ViewHelper\RenderMethod;
use TYPO3Fluid\Fluid\Core\ViewHelper\UndeclaredArgumentException;
use TYPO3Fluid\Fluid\Core\ViewHelper\UnresolvableViewHelperException;
use TYPO3Fluid\Fluid\Tests\Functional\Fixtures\MethodViewHelpers\Formatting;

final class MethodViewHelperCollectionTest extends TestCase
{
    #[Test]
    public function discoversOnlyMarkedMethodsAndTheirAliases(): void
    {
        $collection = new MethodViewHelperCollection(Formatting::class);
        self::assertSame(MethodViewHelperAdapter::class, $collection->resolveViewHelperClassName('greet'));
        self::assertSame('uppercase', $collection->getMethodName('format.upper'));
        self::assertSame(Formatting::class, $collection->getNamespace());
        self::assertArrayNotHasKey('hidden', $collection->getMethods());
        self::assertSame(['name', 'greeting'], array_keys(RenderMethod::fromClass(Formatting::class, 'greet')->getArgumentDefinitions()));
        self::assertSame([], RenderMethod::fromClass(Formatting::class, 'environment')->getArgumentDefinitions());
    }

    #[Test]
    public function inheritedMethodsAreSupported(): void
    {
        $object = new class () extends Formatting {};
        $collection = new MethodViewHelperCollection($object::class);
        self::assertSame('greet', $collection->getMethodName('greet'));
    }

    #[Test]
    public function constructorsAreNotCalledDuringDiscovery(): void
    {
        $class = new class (false) {
            public function __construct(bool $fail)
            {
                if ($fail) {
                    throw new \LogicException('Constructor executed.');
                }
            }

            #[ViewHelper]
            public function value(): string
            {
                return '';
            }
        };
        // A required constructor argument proves that reflection does not instantiate the class.
        self::assertSame(['value' => 'value'], (new MethodViewHelperCollection($class::class))->getMethods());
    }

    public static function invalidMethods(): iterable
    {
        yield 'abstract class' => [AbstractViewHelper::class, 1792401001];
        yield 'non-public method' => [(new class () {
            #[ViewHelper]
            protected function value(): void {}
        })::class, 1792320002];
        yield 'static method' => [(new class () {
            #[ViewHelper]
            public static function value(): void {}
        })::class, 1792320002];
        yield 'duplicate alias' => [(new class () {
            #[ViewHelper(name: 'value')]
            public function first(): void {}
            #[ViewHelper(name: 'value')]
            public function second(): void {}
        })::class, 1792401003];
        yield 'invalid alias' => [(new class () {
            #[ViewHelper(name: 'bad name')]
            public function value(): void {}
        })::class, 1792401003];
        yield 'undeclared content' => [(new class () {
            #[ViewHelper(contentArgumentName: 'value')]
            public function value(): void {}
        })::class, 1792401004];
        yield 'duplicate request' => [(new class () {
            #[ViewHelper]
            public function value(?ServerRequestInterface $first, ?ServerRequestInterface $second): void {}
        })::class, 1792401007];
        yield 'request reference' => [(new class () {
            #[ViewHelper]
            public function value(?ServerRequestInterface &$request): void {}
        })::class, 1792401007];
        yield 'unannotated required argument' => [(new class () {
            #[ViewHelper]
            public function value(string $value): void {}
        })::class, 1792320004];
        yield 'variadic argument' => [(new class () {
            #[ViewHelper]
            public function value(#[Argument('')] string ...$value): void {}
        })::class, 1792320005];
    }

    #[Test]
    #[DataProvider('invalidMethods')]
    public function rejectsInvalidDefinitions(string $className, int $code): void
    {
        self::expectException(InvalidArgumentDefinitionException::class);
        self::expectExceptionCode($code);
        new MethodViewHelperCollection($className);
    }

    #[Test]
    public function directInvocationRejectsMissingRequiredArguments(): void
    {
        self::expectException(MissingArgumentException::class);
        $context = new RenderingContext();
        $context->getViewHelperInvoker()->invokeMethod(Formatting::class, 'greet', [], $context);
    }

    #[Test]
    public function directInvocationRejectsAdditionalArguments(): void
    {
        self::expectException(UndeclaredArgumentException::class);
        $context = new RenderingContext();
        $context->getViewHelperInvoker()->invokeMethod(Formatting::class, 'request', ['request' => 'fake'], $context);
    }

    #[Test]
    public function directInvocationRejectsUnmarkedMethods(): void
    {
        self::expectException(UnresolvableViewHelperException::class);
        $context = new RenderingContext();
        $context->getViewHelperInvoker()->invokeMethod(Formatting::class, 'hidden', [], $context);
    }
}
