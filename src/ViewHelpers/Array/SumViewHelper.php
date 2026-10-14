<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace TYPO3Fluid\Fluid\ViewHelpers\Array;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\InvalidArgumentValueException;

/**
 * The SumViewHelper calculates the sum of all values in an array.
 * All values must be numeric. The sum of an empty array is 0.
 *
 * This ViewHelper mimicks PHP's :php:`array_sum()` function.
 *
 * Examples
 * ========
 *
 * Sum of all values
 * -----------------
 *
 * Given ``prices = [10, 2.5, '7']``:
 *
 * ::
 *
 *    <f:array.sum value="{prices}" />
 *
 * .. code-block:: text
 *
 *    19.5
 *
 *
 * Inline usage
 * ------------
 *
 * ::
 *
 *    Total: {prices -> f:array.sum()}
 *
 * .. code-block:: text
 *
 *    Total: 19.5
 *
 * @api
 * @see https://docs.typo3.org/permalink/fluid:typo3fluid-fluid-array-sum
 */
final class SumViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('value', 'array', 'The array of numeric values to sum up');
    }

    /**
     * @return int|float The sum of all values
     */
    public function render(): int|float
    {
        $value = $this->arguments['value'] ?? $this->renderChildren();
        if ($value === null || !is_iterable($value)) {
            $givenType = get_debug_type($value);
            throw new InvalidArgumentValueException(
                'The argument "value" was registered with type "array", but is of type "'
                . $givenType . '" in view helper "' . static::class . '".',
                1792005140,
            );
        }
        $value = iterator_to_array($value);
        foreach ($value as $item) {
            if (!is_numeric($item)) {
                throw new InvalidArgumentValueException(
                    'All values of the argument "value" must be numeric, but one is of type "'
                    . get_debug_type($item) . '" in view helper "' . static::class . '".',
                    1792005141,
                );
            }
        }
        return array_sum($value);
    }
}
