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
:php:`TYPO3Fluid\Fluid\Core\ViewHelper\Attribute\Argument`. Each ViewHelper must
use one declaration style: attributed definitions are not merged with
:php:`initializeArguments()` registrations, including inherited ones. Different
ViewHelpers in the same project may use different styles.

For example:

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
            #[Argument(description: 'Step size.')]
            int $step = 1,
        ): string {
            return implode(', ', range($start, $end, $step));
        }
    }

The parameter name becomes the template argument name, and its PHP type becomes
the argument type. An untyped parameter uses :php:`mixed`. Whether an argument
is required and its default value come from the PHP parameter declaration.
A parameter without a default value is required, including nullable parameters
such as :php:`?string $value`. A parameter with a default value is optional:
for example, :php:`int $step = 1` or :php:`?string $value = null`.
The attribute only supplies :php:`description` and :php:`escape`, with the same
meaning as the corresponding :php:`registerArgument()` parameters.
The description must be supplied explicitly; an empty string is valid, for
example :php:`#[Argument('')]`.

.. important::

    Choose one argument declaration style per ViewHelper. As soon as a
    :php:`render()` parameter has an :php:`Argument` attribute, the attributed
    parameters provide the complete argument definitions. Fluid does not call
    :php:`initializeArguments()`, including inherited implementations, and does
    not merge arguments registered there with the attributed definitions.

    Different ViewHelpers in the same project may use different declaration
    styles. The restriction applies to each individual ViewHelper, not to the
    project as a whole.

Existing ViewHelpers using :php:`initializeArguments()` continue to work.
For subclasses of
:php:`AbstractViewHelper`, :php:`initialize()` is still called before rendering.
Argument processing, validation, escaping and compiled templates use the same
pipeline as other ViewHelpers. Pass-by-reference,
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

Implementing ViewHelperInterface directly
----------------------------------------

Argument attributes also work with ViewHelpers that implement
:php:`TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperInterface` directly.
The resolver discovers the argument definitions, and the invoker passes the
processed arguments to the public :php:`render()` method. Rendering context
injection works in the same way as for subclasses of :php:`AbstractViewHelper`.

These classes still implement all methods of :php:`ViewHelperInterface`,
including the setters, additional argument validation and :php:`convert()`
for compilation. When attributed parameters are present, Fluid obtains the
definitions without calling :php:`prepareArguments()`. After setting arguments
and the rendering context and dispatching the argument validation event, it
calls :php:`render()` directly instead of :php:`initializeArgumentsAndRender()`.
Use the setters for any setup required before rendering.

Legacy interface-only implementations without render method arguments continue
to use :php:`prepareArguments()` and :php:`initializeArgumentsAndRender()`.
Subclasses of :php:`AbstractViewHelper` retain their existing rendering lifecycle,
including custom overrides of :php:`initializeArgumentsAndRender()`.

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
