<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Attribute\Argument;
use TYPO3Fluid\Fluid\Core\ViewHelper\Attribute\ViewHelper;
use TYPO3Fluid\Fluid\Schema\ViewHelperFinder;
use TYPO3Fluid\Fluid\Schema\ViewHelperMetadataFactory;
use TYPO3Fluid\Fluid\Tests\Functional\Fixtures\MethodViewHelpers\Formatting;

final class MethodViewHelperMetadataTest extends TestCase
{
    #[Test]
    public function reflectsMethodDocumentationArgumentsAndAliases(): void
    {
        $factory = new ViewHelperMetadataFactory();
        $metadata = $factory->createFromViewHelperMethod(Formatting::class, 'greet');
        self::assertSame('Greet a template user.', $metadata->documentation);
        self::assertSame(['@see' => 'https://example.com/greetings'], $metadata->docTags);
        self::assertSame(['name', 'greeting'], array_keys($metadata->argumentDefinitions));
        self::assertTrue($metadata->argumentDefinitions['name']->isRequired());
        self::assertSame('Hello', $metadata->argumentDefinitions['greeting']->getDefaultValue());
        self::assertSame('format.upper', $factory->createFromViewHelperMethod(Formatting::class, 'uppercase')->tagName);
    }

    #[Test]
    public function metadataDiscoveryDoesNotConstructOrdinaryClasses(): void
    {
        $object = new class (false) {
            public function __construct(bool $fail)
            {
                if ($fail) {
                    throw new \LogicException('Constructor executed.');
                }
            }

            #[ViewHelper]
            public function value(#[Argument('')] string $value = '', ?RenderingContextInterface $context = null, ?ServerRequestInterface $request = null): string
            {
                throw new \LogicException('The method must not be executed.');
            }
        };
        $metadata = (new ViewHelperFinder())->findViewHelpersInClass($object::class);
        self::assertCount(1, $metadata);
        self::assertSame(['value'], array_keys($metadata[0]->argumentDefinitions));
    }

    #[Test]
    public function unmarkedMethodsHaveNoViewHelperMetadata(): void
    {
        self::expectException(\InvalidArgumentException::class);
        (new ViewHelperMetadataFactory())->createFromViewHelperMethod(Formatting::class, 'hidden');
    }
}
