.. include:: /Includes.rst.txt

.. _creating-viewhelpers:

====================
Creating ViewHelpers
====================

Creating a ViewHelper is extremely simple. First, make sure you read the
:doc:`chapter about using ViewHelpers </Usage/ViewHelpers>` so you know where
ViewHelper class files are expected to be placed in your own package and that
you understand how/why you would require a custom ViewHelper.

Let's create an example ViewHelper which will accept exactly two arguments, both
arrays, and use those arrays to create a new array using `array_combine` which
takes one argument with keys and another of the same size with values. We would
like this new ViewHelper to be usable in inline syntax - for example as value of
the `each` argument on `f:for` to iterate the combined array. And finally, we
would like to be able to specify the "values" array also in the special inline
syntax for tag content:

.. code-block:: xml

    <html xmlns:mypkg="Vendor\Package\ViewHelpers">
    <dl>
        <f:for each="{myValuesArray -> mypkg:combine(keys: myKeysArray)}" as="item" key="key">
            <dt>{key}</dt>
            <dd>{item}</dd>
        </f:for>
    <dl>
   </html>

To enable this usage we must then create a ViewHelper class:

.. code-block:: php

    <?php
    namespace Vendor\Package\ViewHelpers;

    use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

    /**
     * This ViewHelper takes two arrays and returns
     * the `array_combine`d result.
     */
    class CombineViewHelper extends AbstractViewHelper
    {
        public function initializeArguments(): void
        {
            $this->registerArgument('values', 'array', 'Values to use in array_combine');
            $this->registerArgument('keys', 'array', 'Keys to use in array_combine', true);
        }

        /**
         * Combines two arrays using one for keys and
         * the other for values. If values are not provided
         * in argument it can be provided as tag content.
         */
        public function render(): array
        {
            $values = $this->arguments['values'];
            $keys = $this->arguments['keys'];
            if ($values === null) {
                $values = $this->renderChildren();
            }
            return array_combine($keys, $values);
        }
    }

And that's all this class requires to work in the described way.

Note that in this example the ViewHelper's `render()` method returns an array
which means you can't use it in tag mode:

.. code-block:: xml

    <html xmlns:mypkg="Vendor\Package\ViewHelpers">
    <!-- BAD USAGE. Will output string value "Array" -->
    <mypkg:combine keys="{myKeysArray}">{myValuesArray}</mypkg:combine>
    </html>

Naturally this implies that any ViewHelper which returns a string compatible
value (including numbers and objects which have a `__toString()` method) can be
used in tag mode or inline mode - whereas ViewHelpers that return other data
types normally only make sense to use in inline mode; as values of other
ViewHelpers' attributes that require the returned value type. For example,
ViewHelpers which format output should always return a string (examples of such
ViewHelpers might be ones that implement `strip_tags`, `nl2br` or other
string-manipulating PHP functions). And data ViewHelpers may return any type,
but must be used a bit more carefully.

In other words: be careful what data types your ViewHelper returns.
Non-string-compatible values may cause problems if you use the ViewHelper in
ways that were not intended. Like in PHP, data types must either match or be
mutually compatible.

.. _viewhelper-argument-attributes:

Declaring arguments with PHP attributes
======================================

As an alternative to :php:`initializeArguments()`, arguments can be declared on
the parameters of :php:`render()` using
:php:`TYPO3Fluid\Fluid\Core\ViewHelper\Attribute\Argument`:

.. code-block:: php

    use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
    use TYPO3Fluid\Fluid\Core\ViewHelper\Attribute\Argument;

    class RangeViewHelper extends AbstractViewHelper
    {
        public function render(
            #[Argument(description: 'First value of the sequence.')]
            int $start,
            #[Argument(description: 'Last possible value of the sequence.')]
            int $end,
            #[Argument(description: 'Step size.', optional: true, defaultValue: 1)]
            int $step,
        ): string {
            return implode(', ', range($start, $end, $step));
        }
    }

The parameter name becomes the template argument name, and its PHP type becomes
the argument type. An untyped parameter uses :php:`mixed`. Arguments are required
unless :php:`optional: true` is specified, including nullable parameters.
Optional arguments use :php:`defaultValue` from the attribute, or the PHP
parameter's default if the attribute omits it. Without either default, an
optional parameter must accept :php:`null`. Required arguments cannot have a
default value. The attribute also accepts :php:`escape`, with the same meaning
as the corresponding :php:`registerArgument()` parameter.

When attributes declare arguments, :php:`initializeArguments()` is not called.
Use one declaration style per ViewHelper. Existing ViewHelpers using
:php:`initializeArguments()` continue to work. :php:`initialize()` is still
called before rendering, and argument processing, validation, escaping and
compiled templates use the same pipeline as other ViewHelpers. Pass-by-reference,
variadic and intersection-type arguments are not supported.

A parameter typed as
:php:`TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface` without an
:php:`Argument` attribute receives the current rendering context. It is not
exposed as a template argument. Other required parameters need an
:php:`Argument` attribute; unannotated optional parameters retain their PHP
defaults. Services can be injected through the ViewHelper constructor as usual.

For attributed ViewHelpers, Fluid's metadata factory reads the definitions
without calling the ViewHelper's constructor, :php:`prepareArguments()`,
:php:`initializeArguments()` or :php:`render()`. Tooling can also obtain these
definitions through
:php:`TYPO3Fluid\Fluid\Core\ViewHelper\RenderMethod::fromClass($className)->getArgumentDefinitions()`.

.. _invoking-viewhelpers:

Invoking other ViewHelpers
==========================

..  warning::
    In general, it is advised to keep ViewHelpers small and to extract more
    complicated code into its own service class.

In some cases, it might be helpful or necessary to call a ViewHelper from
another ViewHelper, for example to reuse its functionality. This can be
achieved by using the :php:`ViewHelperInvoker`:

.. code-block:: php

    $result = $this->renderingContext->getViewHelperInvoker()->invoke(
        \TYPO3Fluid\Fluid\ViewHelpers\Format\CdataViewHelper::class,
        ['value' => 'some input text < & >'],
        $this->renderingContext,
    );

.. _nodeinitialized:

NodeInitialized Event
=====================

..  versionadded:: Fluid 4.2

In addition to the API of :php:`AbstractViewHelper`, ViewHelpers can hook into
the parsing process by implementing :php:`ViewHelperNodeInitializedEventInterface`.
The interface requires the ViewHelper class to implement an additional static method
`nodeInitializedEvent()`, which is called during the initial parsing of a
template that uses the ViewHelper. In the method, you receive the current
parsing state of the template as well as relevant information about the
ViewHelper call, which allows for additional syntax validation and special
low-level processing.

The following example ensures that the ViewHelper can only be used on the first
nesting level and thus cannot be nested into other ViewHelpers:

.. code-block:: php

    use TYPO3Fluid\Fluid\Core\Parser\ParsingState;
    use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;
    use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
    use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperNodeInitializedEventInterface;

    final class FooViewHelper extends AbstractViewHelper implements ViewHelperNodeInitializedEventInterface
    {
        public static function nodeInitializedEvent(ViewHelperNode $node, array $arguments, ParsingState $parsingState): void
        {
            if ($parsingState->hasNodeTypeInStack(ViewHelperNode::class)) {
                throw new \TYPO3Fluid\Fluid\Core\Parser\Exception(sprintf(
                    'FooViewHelper needs to be defined at the root level of the template.',
                ), 1750671904);
            }
        }
    }

This event is used by Fluid internally in the :php:`SectionViewHelper`,
the :php:`LayoutViewHelper` and the :php:`ArgumentViewHelper`.

..  deprecated:: 4.2
    NodeInitialized event replaces the old `AbstractViewHelper::postParseEvent()` method,
    which will no longer work with Fluid 5.
