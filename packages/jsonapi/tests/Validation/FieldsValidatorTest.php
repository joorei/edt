<?php

declare(strict_types=1);

namespace Tests\Validation;

use EDT\JsonApi\ApiDocumentation\AttributeTypeResolver;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use EDT\JsonApi\Validation\FieldsException;
use EDT\JsonApi\Validation\FieldsValidator;
use EDT\Querying\ConditionFactories\PhpConditionFactory;
use EDT\Querying\PropertyAccessors\ReflectionPropertyAccessor;
use EDT\Querying\Utilities\ConditionEvaluator;
use EDT\Querying\Utilities\Sorter;
use EDT\Querying\Utilities\TableJoiner;
use EDT\Wrapping\TypeProviders\LazyTypeProvider;
use EDT\Wrapping\TypeProviders\PrefilledTypeProvider;
use EDT\Wrapping\Utilities\PhpEntityVerifier;
use EDT\Wrapping\Utilities\PropertyPathProcessorFactory;
use EDT\Wrapping\Utilities\SchemaPathProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Tests\data\ApiTypes\AuthorType;
use Tests\data\ApiTypes\BookType;
use Tests\data\Types\BirthType;

class FieldsValidatorTest extends TestCase
{
    private FieldsValidator $fieldsValidator;

    private PrefilledTypeProvider $typeProvider;

    protected function setUp(): void
    {
        parent::setUp();
        $conditionFactory = new PhpConditionFactory();
        $lazyTypeProvider = new LazyTypeProvider();
        $propertyAccessor = new ReflectionPropertyAccessor();
        $propertyPathProcessorFactory = new PropertyPathProcessorFactory();
        $schemaPathProcessor = new SchemaPathProcessor($propertyPathProcessorFactory, $lazyTypeProvider);
        $tableJoiner = new TableJoiner($propertyAccessor);
        $conditionEvaluator = new ConditionEvaluator($tableJoiner);
        $sorter = new Sorter($tableJoiner);
        $entityVerifier = new PhpEntityVerifier($schemaPathProcessor, $conditionEvaluator, $sorter);
        $attributeTypeResover = new AttributeTypeResolver();
        $this->typeProvider = new PrefilledTypeProvider([
            new AuthorType($conditionFactory, $lazyTypeProvider, $propertyAccessor, $entityVerifier, $attributeTypeResover),
            new BookType($conditionFactory, $lazyTypeProvider, $propertyAccessor, $attributeTypeResover, $entityVerifier),
            new BirthType($conditionFactory),
        ]);
        $lazyTypeProvider->setAllTypes($this->typeProvider);
        $this->fieldsValidator = new FieldsValidator(Validation::createValidator());
    }

    /**
     * @dataProvider getFieldsValues()
     */
    public function testGetNonReadableProperties(string $typeIdentifier, string $propertiesString, array $expectedNonReadables): void
    {
        $type = $this->typeProvider->requestType($typeIdentifier)
            ->instanceOf(ResourceTypeInterface::class)
            ->getInstanceOrThrow();
        $nonReadables = $this->fieldsValidator->getNonReadableProperties($propertiesString, $type);
        self::assertSame($expectedNonReadables, $nonReadables);
    }

    /**
     * @dataProvider getInvalidFieldsFormats()
     */
    public function testValidateFormatException($fields): void
    {
        $this->expectException(FieldsException::class);

        $this->fieldsValidator->validateFormat($fields);
    }

    /**
     * @dataProvider getValidFieldsFormats()
     */
    public function testValidateFormat($fields): void
    {
        $validatedFields = $this->fieldsValidator->validateFormat($fields);
        self::assertSame($fields, $validatedFields);
    }

    public function getInvalidFieldsFormats(): array
    {
        return array_map(static fn ($fields): array => [$fields], [
            [1 => 'x'],
            [-1 => 'x'],
            [0 => 'x'],
            ['1' => 'x'],
            ['-1' => 'x'],
            ['0' => 'x'],
            ['Foo' => 'a*'],
            ['Foo' => '1a'],
            ['Foo' => 'a-b'],
            ['Foo' => 'a.b'],
            ['Foo' => 'a,a.b'],
            ['Foo' => 'B'],
            ['Foo' => 'Bar'],
            null,
            'Foo',
            'foo',
            1,
            -1,
            0,
            ['' => 'x'],
        ]);
    }

    public function getValidFieldsFormats(): array
    {
        return array_map(static fn ($fields): array => [$fields], [
            [],
            ['Foo' => ''],
            ['Foo' => 'x'],
            ['foo' => 'x'],
            ['³›‹²' => 'x'],
            ['Foo' => 'x,y,z'],
            ['Foo' => 'foo123'],
            ['Foo' => 'fooBar'],
            ['Foo' => 'x', 'Bar' => 'x'],
        ]);
    }

    public function getFieldsValues(): array
    {
        return [
            ['Tests\data\ApiTypes\AuthorType', '', []],
            ['Tests\data\ApiTypes\BookType', '', []],
            ['Tests\data\ApiTypes\AuthorType', 'name', []],
            ['Tests\data\ApiTypes\AuthorType', 'name,pseudonym,books', []],
            ['Tests\data\ApiTypes\AuthorType', 'pseudonym', []],
            ['Tests\data\ApiTypes\AuthorType', 'pseudonymm', ['pseudonymm']],
            ['Tests\data\ApiTypes\AuthorType', 'books', []],
            ['Tests\data\ApiTypes\AuthorType', 'writtenBooks', ['writtenBooks']],
            ['Tests\data\ApiTypes\AuthorType', 'books,writtenBooks', ['writtenBooks']],
            ['Tests\data\ApiTypes\BookType', 'title', []],
            ['Tests\data\ApiTypes\BookType', 'author', []],
            ['Tests\data\ApiTypes\BookType', 'tags', []],
            ['Tests\data\ApiTypes\BookType', 'title,author,tags', []],
            ['Tests\data\ApiTypes\BookType', 'title,author,tags,foo', ['foo']],
            ['Tests\data\ApiTypes\BookType', 'foo,tags', ['foo']],
            ['Tests\data\ApiTypes\BookType', 'foo tags', ['foo tags']],
            ['Tests\data\ApiTypes\BookType', 'tags title', ['tags title']],
            ['Tests\data\ApiTypes\BookType', 'tags,title', []],
            ['Tests\data\ApiTypes\BookType', 'tags, title', [' title']],
        ];
    }
}
