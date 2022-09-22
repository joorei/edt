<?php

declare(strict_types=1);

namespace EDT\Querying\Utilities;

use InvalidArgumentException;
use function is_array;
use function array_slice;
use function count;
use function is_int;

/**
 * @internal
 */
class Iterables
{
    private function __construct() {}

    /**
     * Maps each given value to an array using a given callable. All arrays will be merged
     * (flatted) into a single one and returned.
     *
     * @template V
     * @template R
     *
     * @param callable(V): list<R> $callable how to map each given value to an array
     * @param array<int|string, V>      $values   the values to be mapped to an array
     *
     * @return list<R>
     */
    public static function mapFlat(callable $callable, array $values): array
    {
        // Do not remove this check: there was a problem when passing no arguments
        // to array_merge, which could not be reproduced with tests.
        $mappedArray = array_map($callable, $values);
        if ([] === $mappedArray) {
            return [];
        }

        return array_merge(...$mappedArray);
    }

    /**
     * Split the given iterable into multiple arrays.
     *
     * Can be used to revert a {@link Iterables::mapFlat} operation.
     *
     * @template V
     *
     * @param iterable<V> $toSplit The array to split. Length must be equal to the sum of $sizes, otherwise the behavior is undefined.
     * @param bool $preserveKeys   If the given iterable has usable keys setting this parameter to `true` will
     *                             preserve them. Otherwise, each resulting nested array will be re-indexed
     *                             with integer keys (starting with `0`).
     * @param int ...$sizes        The intended array size of each item in the result array.
     *
     * @return list<array<int|string,V>> The nested result array, same length as the $sizes array.
     */
    public static function split(iterable $toSplit, bool $preserveKeys, int ...$sizes): array
    {
        $toSplit = self::asArray($toSplit);

        $result = [];
        $valuesOffset = 0;
        foreach ($sizes as $count) {
            $slice = array_slice($toSplit, $valuesOffset, $count, $preserveKeys);
            if (!$preserveKeys) {
                // array_slice always preserves strings keys; this extra step ensures re-indexing
                $slice = array_values($slice);
            }
            $result[] = $slice;
            $valuesOffset += $count;
        }

        return $result;
    }

    /**
     * Restructures the given target recursively. The recursion stops when the specified depth
     * is reached or the current target is not iterable.
     *
     * If at any level the current target is non-iterable then the depth will be assumed to be
     * this level, even if the actual $depth is given with a greater value.
     *
     * @param mixed $target
     * @param int $depth Passing 0 will return the given target wrapped in an array.
     *                   Passing 1 will keep the structure of the given target.
     *                   Passing a value greater 1 will flat the target from the top to the
     *                   bottom, meaning a target with three levels and a depth of 2 will keep the
     *                   third level as it is but flattens the first two levels.
     * @param (callable(mixed):bool)|null $isIterable Function to determine if the
     *                   current target should be considered iterable and thus flatted.
     *                   Defaults to {@link is_iterable()} if `null` is given.
     *
     * @return list<mixed>
     * @throws InvalidArgumentException If a negative value is passed as $depth
     */
    public static function restructureNesting($target, int $depth, callable $isIterable = null): array
    {
        if (null === $isIterable) {
            $isIterable = 'is_iterable';
        }
        if (0 > $depth) {
            throw new InvalidArgumentException("depth must be 0 or positive, is $depth instead");
        }
        if (0 === $depth || !$isIterable($target)) {
            return [$target];
        }

        return self::mapFlat(static function ($newTarget) use ($depth): array {
            return self::restructureNesting($newTarget, $depth - 1);
        }, self::asArray($target));
    }

    /**
     * @template T
     * @param iterable<T> $iterable
     * @return array<int|string, T>
     */
    public static function asArray(iterable $iterable): array
    {
        return is_array($iterable) ? $iterable : iterator_to_array($iterable);
    }

    /**
     * @param iterable<mixed> $values
     * @throws InvalidArgumentException Thrown if the number of values in the iterable is not equal the given count value.
     */
    public static function assertCount(int $count, iterable $values): void
    {
        $valueCount = count(self::asArray($values));
        if ($count !== $valueCount) {
            throw new InvalidArgumentException("Expected exactly $count parameter, got $valueCount");
        }
    }

    /**
     * @template T
     * @param iterable<T> $propertyValues
     * @return T
     */
    public static function getOnlyValue(iterable $propertyValues)
    {
        self::assertCount(1, $propertyValues);
        $array = self::asArray($propertyValues);
        return array_pop($array);
    }

    /**
     * Compares all values with each other using the given $equalityComparison
     * callback. If equal values are found, the one with the higher index will be
     * replaced with the index of the first occurrence in the array.
     *
     * The type T of the given values must not be `int`.
     *
     * @template T
     * @param callable(T,T):bool $equalityComparison
     * @param non-empty-list<T> $values
     * @return non-empty-list<T|int<0, max>>
     */
    public static function setReferences(callable $equalityComparison, array $values): array
    {
        $count = count($values);
        for ($i = 0; $i < $count; $i++) {
            $valueToCheckIfToUseAsIndex = $values[$i];
            if (!is_int($valueToCheckIfToUseAsIndex)) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $valueToCheckAgainst = $values[$j];
                    if (!is_int($valueToCheckAgainst)
                        && $equalityComparison($valueToCheckIfToUseAsIndex, $valueToCheckAgainst)) {
                        $values[$j] = $i;
                    }
                }
            }
        }
        return $values;
    }

    /**
     * Will iterate through the given array and inserts the given value into each of its values
     * (which are expected to be an array too).
     *
     * @param array<string|int, list<mixed>> $array
     * @param mixed $value
     */
    public static function insertValue(array &$array, int $index, $value): void
    {
        array_walk($array, static function (&$arrayValue) use ($index, $value): void {
            array_splice($arrayValue, $index, 0, [$value]);
        });
    }

    /**
     * This method is **not** intended as a general replacement for empty checks but intended to be used as callback, e.g.
     * ```
     * array_filter($array, [Iterables::class, 'isEmpty']);
     * ```
     *
     * Technically it would be possible to use this method in `if` conditions too,
     * but this is discouraged because it can be considered less readable than a
     * simple `if ([] === $array) {`.
     *
     * @param array<string|int, mixed> $value
     */
    public static function isEmptyArray(array $value): bool
    {
        return [] === $value;
    }
}
