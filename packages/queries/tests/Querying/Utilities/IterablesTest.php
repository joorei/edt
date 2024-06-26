<?php

declare(strict_types=1);

namespace Tests\Querying\Utilities;

use EDT\Querying\Utilities\Iterables;
use Tests\data\Model\Person;
use Tests\ModelBasedTest;

class IterablesTest extends ModelBasedTest
{
    public function testFlatWithArray(): void
    {
        $input = [
            [1, 2, 3],
            [4, 5, 6],
        ];

        $output = Iterables::mapFlat(
            static fn (array $arrayElement): array => $arrayElement,
            $input
        );

        self::assertEquals([1, 2, 3, 4, 5, 6], $output);
    }

    public function testFlatWithEmptyArray(): void
    {
        $input = [];
        $output = Iterables::mapFlat(static function (array $arrayElement): array {
            self::fail();
        }, $input);

        self::assertEquals([], $output);
    }

    public function testFlatWithObjects(): void
    {
        $output = Iterables::mapFlat(
            static fn (Person $author): array => Iterables::asArray($author->getBooks()),
            array_values($this->authors)
        );

        self::assertEquals(array_values($this->books), $output);
    }

    public function testSplitSingle(): void
    {
        $input = ['x' => 'a', 'y' => 'b', 'z' => 'c'];
        $expected = [
            ['x' => 'a', 'y' => 'b', 'z' => 'c'],
        ];
        $output = Iterables::split($input, [3]);

        self::assertEquals($expected, $output);
    }

    public function testSplitEmpty(): void
    {
        $output = Iterables::split([], []);
        self::assertEquals([], $output);
    }

    public function testSplitWithEmptiesOnly(): void
    {
        $output = Iterables::split([], [0, 0, 0]);
        self::assertEquals([[], [], []], $output);
    }

    public function testSplitWithEmptiesInserted(): void
    {
        $input = [1, 2, 3];
        $expected = [[], [0 => 1], [], [0 => 2, 1 => 3], [], []];
        $output = Iterables::split($input, [0, 1, 0, 2, 0, 0]);

        self::assertEquals($expected, $output);
    }

    public function testSplitWithEmptiesInsertedAndPreservedIntKeys(): void
    {
        $input = [1, 2, 3];
        $expected = [[], [1], [], [0 => 2, 1 => 3], [], []];
        $output = Iterables::split($input, [0, 1, 0, 2, 0, 0]);

        self::assertEquals($expected, $output);
    }

    public function testSplitWithStringKeys(): void
    {
        $input = ['x' => 'a', 'y' => 'b', 'z' => 'c'];
        $expected = [['x' => 'a', 'y' => 'b'], ['z' => 'c']];
        $output = Iterables::split($input, [2, 1]);

        self::assertEquals($expected, $output);
    }

    public function testSplitWithIntKeys(): void
    {
        $input = [3 => 'a', 7 => 'b', 1 => 'c'];
        $expected = [[0 => 'a', 1 => 'b'], [0 => 'c']];
        $output = Iterables::split($input, [2, 1]);

        self::assertEquals($expected, $output);
    }

    public function testSplitWithPreservedStringKeys(): void
    {
        $input = ['x' => 'a', 'y' => 'b', 'z' => 'c'];
        $expected = [['x' => 'a', 'y' => 'b'], ['z' => 'c']];
        $output = Iterables::split($input, [2, 1]);

        self::assertEquals($expected, $output);
    }

    public function testSplitWithPreservedIntKeys(): void
    {
        $input = [3 => 'a', 7 => 'b', 1 => 'c'];
        $expected = [[0 => 'a', 1 => 'b'], [0 => 'c']];
        $output = Iterables::split($input, [2, 1]);

        self::assertEquals($expected, $output);
    }
}
