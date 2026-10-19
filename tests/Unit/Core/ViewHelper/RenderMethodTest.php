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
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;
use TYPO3Fluid\Fluid\Core\ViewHelper\Attribute\Argument;
use TYPO3Fluid\Fluid\Core\ViewHelper\InvalidArgumentDefinitionException;
use TYPO3Fluid\Fluid\Core\ViewHelper\InvalidArgumentValueException;
use TYPO3Fluid\Fluid\Core\ViewHelper\RenderMethod;

final class RenderMethodTest extends TestCase
{
    #[Test]
    public function derivesDefinitionsFromParameters(): void
    {
        $viewHelper = new class () extends AbstractViewHelper {
            public function render(
                #[Argument('Required argument')]
                int $required,
                #[Argument]
                $untyped,
                #[Argument]
                int|string $union,
                #[Argument]
                self $object,
                RenderingContextInterface $context,
                #[Argument(optional: true, defaultValue: 2, escape: false)]
                int $attributeDefault,
                #[Argument(optional: true)]
                string $phpDefault = 'default',
                #[Argument(optional: true, defaultValue: null)]
                ?string $explicitNull = 'ignored',
            ): void {}
        };
        $method = RenderMethod::fromClass($viewHelper::class);
        self::assertEquals([
            'required' => new ArgumentDefinition('required', 'int', 'Required argument', true),
            'untyped' => new ArgumentDefinition('untyped', 'mixed', '', true),
            'union' => new ArgumentDefinition('union', 'string|int', '', true),
            'object' => new ArgumentDefinition('object', $viewHelper::class, '', true),
            'attributeDefault' => new ArgumentDefinition('attributeDefault', 'int', '', false, 2, false),
            'phpDefault' => new ArgumentDefinition('phpDefault', 'string', '', false, 'default'),
            'explicitNull' => new ArgumentDefinition('explicitNull', 'string', '', false),
        ], $method->getArgumentDefinitions());
        self::assertSame($method, RenderMethod::fromClass($viewHelper::class));
    }

    #[Test]
    public function passesProcessedArgumentsAndContextAfterInitialization(): void
    {
        $viewHelper = new class () extends AbstractViewHelper {
            public function initialize(): void
            {
                $this->arguments['value'] .= ' initialized';
            }

            public function render(
                RenderingContextInterface $context,
                #[Argument]
                string $value,
                string $internalDefault = 'internal',
            ): array {
                return [$context, $value, $internalDefault];
            }
        };
        $context = new RenderingContext();
        $viewHelper->setRenderingContext($context);
        $viewHelper->setArguments(['value' => 'test', 'additional' => 'ignored']);
        self::assertSame([$context, 'test initialized', 'internal'], $viewHelper->initializeArgumentsAndRender());
    }

    #[Test]
    public function attributedArgumentsDoNotRunLegacyArgumentInitialization(): void
    {
        $viewHelper = new class () extends AbstractViewHelper {
            public function initializeArguments(): void
            {
                throw new \LogicException('Legacy argument registration must not run.');
            }

            public function render(#[Argument(optional: true, defaultValue: 2)] int $value): int
            {
                return $value;
            }
        };
        $context = new RenderingContext();
        self::assertSame(2, $context->getViewHelperInvoker()->invoke($viewHelper, [], $context));
        self::assertSame(3, $context->getViewHelperInvoker()->invoke($viewHelper, ['value' => '3'], $context));
        self::assertSame(2, $context->getViewHelperInvoker()->invoke($viewHelper, [], $context));
    }

    #[Test]
    public function injectsContextWithoutTemplateArguments(): void
    {
        $viewHelper = new class () extends AbstractViewHelper {
            public function render(RenderingContextInterface $context): RenderingContextInterface
            {
                return $context;
            }
        };
        $context = new RenderingContext();
        self::assertSame([], $viewHelper->prepareArguments());
        self::assertSame($context, $context->getViewHelperInvoker()->invoke($viewHelper, [], $context));
    }

    #[Test]
    public function literalAndUnionTypesUseTheArgumentProcessor(): void
    {
        $viewHelper = new class () extends AbstractViewHelper {
            public function render(#[Argument] string|false $value, #[Argument] true $enabled): array
            {
                return [$value, $enabled];
            }
        };
        $context = new RenderingContext();
        self::assertSame([false, true], $context->getViewHelperInvoker()->invoke($viewHelper, ['value' => false, 'enabled' => true], $context));
        self::assertSame(['test', true], $context->getViewHelperInvoker()->invoke($viewHelper, ['value' => 'test', 'enabled' => true], $context));
        self::expectException(InvalidArgumentValueException::class);
        $context->getViewHelperInvoker()->invoke($viewHelper, ['value' => false, 'enabled' => false], $context);
    }

    public static function invalidRenderMethods(): iterable
    {
        yield 'missing render method' => [new class () extends AbstractViewHelper {}, 1792320001];
        yield 'non-public render' => [new class () extends AbstractViewHelper {
            protected function render(): void {}
        }, 1792320002];
        yield 'static render' => [new class () extends AbstractViewHelper {
            public static function render(): void {}
        }, 1792320002];
        yield 'duplicate context' => [new class () extends AbstractViewHelper {
            public function render(RenderingContextInterface $first, RenderingContextInterface $second): void {}
        }, 1792320003];
        yield 'unannotated required parameter' => [new class () extends AbstractViewHelper {
            public function render(string $value): void {}
        }, 1792320004];
        yield 'reference' => [new class () extends AbstractViewHelper {
            public function render(#[Argument] string &$value): void {}
        }, 1792320005];
        yield 'variadic' => [new class () extends AbstractViewHelper {
            public function render(#[Argument] string ...$values): void {}
        }, 1792320005];
        yield 'required attribute default' => [new class () extends AbstractViewHelper {
            public function render(#[Argument(defaultValue: 'default')] string $value): void {}
        }, 1792320006];
        yield 'required PHP default' => [new class () extends AbstractViewHelper {
            public function render(#[Argument] ?string $value = null): void {}
        }, 1792320006];
        yield 'optional non-nullable without default' => [new class () extends AbstractViewHelper {
            public function render(#[Argument(optional: true)] int $value): void {}
        }, 1792320007];
        yield 'intersection' => [new class () extends AbstractViewHelper {
            public function render(#[Argument] \Countable&\Traversable $value): void {}
        }, 1792320008];
    }

    #[Test]
    #[DataProvider('invalidRenderMethods')]
    public function rejectsInvalidRenderMethods(AbstractViewHelper $viewHelper, int $code): void
    {
        self::expectException(InvalidArgumentDefinitionException::class);
        self::expectExceptionCode($code);
        RenderMethod::fromClass($viewHelper::class);
    }
}
