<?php

declare(strict_types=1);

namespace EDT\Querying\Functions;

/**
 * @template-extends AbstractMultiFunction<bool, mixed, array{0: mixed, 1: mixed}>
 */
class SmallerEquals extends AbstractMultiFunction
{
    protected function reduce(array $functionResults): bool
    {
        [$leftValue, $rightValue] = $functionResults;
        return $leftValue <= $rightValue;
    }
}
