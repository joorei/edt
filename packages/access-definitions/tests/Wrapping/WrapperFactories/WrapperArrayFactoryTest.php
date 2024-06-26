<?php

declare(strict_types=1);

namespace Tests\Wrapping\WrapperFactories;

use EDT\ConditionFactory\ConditionFactory;
use EDT\JsonApi\ApiDocumentation\AttributeTypeResolver;
use EDT\JsonApi\InputHandling\PhpEntityRepository;
use EDT\JsonApi\InputHandling\RepositoryInterface;
use EDT\JsonApi\RequestHandling\JsonApiSortingParser;
use EDT\JsonApi\Validation\SortValidator;
use EDT\Querying\ConditionParsers\Drupal\DrupalConditionParser;
use EDT\Querying\ConditionParsers\Drupal\DrupalFilterParser;
use EDT\Querying\ConditionParsers\Drupal\DrupalFilterValidator;
use EDT\Querying\ConditionParsers\Drupal\PredefinedDrupalConditionFactory;
use EDT\Querying\PropertyAccessors\ReflectionPropertyAccessor;
use EDT\Querying\SortMethodFactories\SortMethodFactory;
use EDT\Querying\Utilities\ConditionEvaluator;
use EDT\Querying\Utilities\Sorter;
use EDT\Querying\Utilities\TableJoiner;
use EDT\Wrapping\Contracts\AccessException;
use EDT\Wrapping\Contracts\Types\FilteringTypeInterface;
use EDT\Wrapping\Contracts\Types\TransferableTypeInterface;
use EDT\Wrapping\TypeProviders\LazyTypeProvider;
use EDT\Wrapping\Utilities\PropertyPathProcessorFactory;
use EDT\Wrapping\Utilities\SchemaPathProcessor;
use EDT\Wrapping\WrapperFactories\WrapperArrayFactory;
use EDT\Querying\ObjectProviders\PrefilledEntityProvider;
use EDT\Wrapping\TypeProviders\PrefilledTypeProvider;
use Symfony\Component\Validator\Validation;
use Tests\data\AdModel\Person;
use Tests\data\AdModelBasedTest;
use Tests\data\Types\BirthType;
use Tests\data\Types\AuthorType;
use Tests\data\Types\BookType;
use Webmozart\Assert\Assert;

class WrapperArrayFactoryTest extends AdModelBasedTest
{
    private AuthorType $authorType;

    private ConditionFactory $conditionFactory;

    private PrefilledTypeProvider $typeProvider;

    private ReflectionPropertyAccessor $propertyAccessor;

    private SchemaPathProcessor $schemaPathProcessor;

    private RepositoryInterface $authorRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->conditionFactory = new ConditionFactory();
        $lazyTypeProvider = new LazyTypeProvider();
        $this->propertyAccessor = new ReflectionPropertyAccessor();
        $typeResolver = new AttributeTypeResolver();
        $this->authorType = new AuthorType($this->conditionFactory, $lazyTypeProvider, $this->propertyAccessor, $typeResolver);
        $bookType = new BookType($this->conditionFactory, $lazyTypeProvider, $this->propertyAccessor, $typeResolver);
        $this->typeProvider = new PrefilledTypeProvider([
            $this->authorType,
            $bookType,
            new BirthType($this->conditionFactory),
        ]);
        $lazyTypeProvider->setAllTypes($this->typeProvider);
        $validator = Validation::createValidator();
        $predefinedDrupalConditionFactory = new PredefinedDrupalConditionFactory($this->conditionFactory);
        $filterValidator = new DrupalFilterValidator(
            $validator,
            $predefinedDrupalConditionFactory
        );
        $this->authorRepository = PhpEntityRepository::createDefault(
            $validator,
            new ReflectionPropertyAccessor(),
            $this->authors
        );
        $this->schemaPathProcessor = new SchemaPathProcessor(new PropertyPathProcessorFactory());
    }

    public function testTrue(): void
    {
        self::assertTrue(true);
    }

    public function testListBackingObjectsUnrestricted(): void
    {
        $hasString = $this->conditionFactory->propertyHasStringContainingCaseInsensitiveValue('man', ['pseudonym']);
        $filteredAuthors = $this->listEntities($this->authorType, [$hasString]);
        self::assertCount(1, $filteredAuthors);
        $author = array_pop($filteredAuthors);
        self::assertSame($this->authors['king'], $author);
    }

    public function testListWrappersDepthZero(): void
    {
        $hasString = $this->conditionFactory->propertyHasStringContainingCaseInsensitiveValue('man', ['pseudonym']);
        $filteredAuthors = $this->listEntities($this->authorType, [$hasString]);
        $filteredAuthors = $this->createArrayWrappers($filteredAuthors, $this->authorType, 0);

        $expected = [
            0 => [
                'name'         => 'Stephen King',
                'pseudonym'    => 'Richard Bachman',
                'birthCountry' => 'USA',
                'books'        => [
                    0 => null,
                ],
                'id' => '1',
            ],
        ];

        self::assertEquals($expected, $filteredAuthors);
    }

    public function testListWrappersDepthOne(): void
    {
        $hasString = $this->conditionFactory->propertyHasStringContainingCaseInsensitiveValue('man', ['pseudonym']);
        $filteredAuthors = $this->listEntities($this->authorType, [$hasString]);
        $filteredAuthors = $this->createArrayWrappers($filteredAuthors, $this->authorType, 1);

        $expected = [
            0 => [
                'name'         => 'Stephen King',
                'pseudonym'    => 'Richard Bachman',
                'birthCountry' => 'USA',
                'books'        => [
                    0 => [
                        'author' => null,
                        'tags'   => [],
                        'title'  => 'Doctor Sleep',
                        'id' => '1',
                    ],
                ],
                'id' => '1',
            ],
        ];

        self::assertEquals($expected, $filteredAuthors);
    }

    public function testListWrappersDepthNegative(): void
    {
        $hasString = $this->conditionFactory->propertyHasStringContainingCaseInsensitiveValue('man', ['pseudonym']);
        $filteredAuthors = $this->listEntities($this->authorType, [$hasString]);
        $filteredAuthors = $this->createArrayWrappers($filteredAuthors, $this->authorType, -1);

        $expected = [
            0 => [
                'name'         => 'Stephen King',
                'pseudonym'    => 'Richard Bachman',
                'birthCountry' => 'USA',
                'books'        => [
                    0 => null,
                ],
                'id' => '1',
            ],
        ];

        self::assertEquals($expected, $filteredAuthors);
    }

    public function testListWrappersDepthTwo(): void
    {
        $hasString = $this->conditionFactory->propertyHasStringContainingCaseInsensitiveValue('man', ['pseudonym']);
        $filteredAuthors = $this->listEntities($this->authorType, [$hasString]);
        $filteredAuthors = $this->createArrayWrappers($filteredAuthors, $this->authorType, 2);

        $expected = [
            0 => [
                'name'         => 'Stephen King',
                'pseudonym'    => 'Richard Bachman',
                'birthCountry' => 'USA',
                'books'        => [
                    0 => [
                        'author' => [
                            'name'         => 'Stephen King',
                            'pseudonym'    => 'Richard Bachman',
                            'birthCountry' => 'USA',
                            'books'        => [
                                0 => null,
                            ],
                            'id' => '1',
                        ],
                        'tags'   => [],
                        'title'  => 'Doctor Sleep',
                        'id' => '1',
                    ],
                ],
                'id' => '1',
            ],
        ];

        self::assertEquals($expected, $filteredAuthors);
    }

    public function testListWrappersWithMapping(): void
    {
        $hasString = $this->conditionFactory->propertyHasStringContainingCaseInsensitiveValue('Oranje', ['birthCountry']);
        $filteredAuthors = $this->listEntities($this->authorType, [$hasString]);
        $filteredAuthors = $this->createArrayWrappers($filteredAuthors, $this->authorType, 0);

        $expected = [
            0 => [
                'name'         => 'John Ronald Reuel Tolkien',
                'pseudonym'    => null,
                'birthCountry' => 'Oranje-Freistaat',
                'books'        => [
                    0 => null,
                ],
                'id' => '2',
            ],
        ];

        self::assertEquals($expected, $filteredAuthors);
    }

    public function testGetAuthorWrapper(): void
    {
        $fetchedAuthor = $this->getEntityByIdentifier($this->authorType,'2');

        $expected = [
            'name'         => 'John Ronald Reuel Tolkien',
            'pseudonym'    => null,
            'birthCountry' => 'Oranje-Freistaat',
            'books'        => [
                0 => null,
            ],
            'id' => '2',
        ];

        self::assertEquals($expected, $fetchedAuthor);
    }

    public function testGetAuthorObject(): void
    {
        $fetchedAuthor = $this->getEntityByIdentifier($this->authorType,'2', false);

        self::assertSame($this->authors['tolkien'], $fetchedAuthor);
    }

    private function createWrapperArrayFactory(int $depth): WrapperArrayFactory
    {
        return new WrapperArrayFactory($this->propertyAccessor, $depth);
    }

    private function createArrayWrappers(array $entities, TransferableTypeInterface $type, int $depth): array
    {
        $wrapper = $this->createWrapperArrayFactory($depth);
        return array_values(array_map(
            static fn (object $entity) => $wrapper->createWrapper($entity, $type),
            $entities
        ));
    }

    private function listEntities(FilteringTypeInterface $type, array $conditions): array
    {
        $this->schemaPathProcessor->mapFilterConditions($type, $conditions);
        $conditions = array_merge($conditions, $type->getAccessConditions());

        return $this->authorRepository->getEntities($conditions, []);
    }

    /**
     * @param non-empty-string $identifier
     */
    public function getEntityByIdentifier(FilteringTypeInterface&TransferableTypeInterface $type, string $identifier, bool $wrap = true)
    {
        $idPropertyLink = $type->getFilteringProperties()['id'];
        Assert::null($idPropertyLink->getAvailableTargetProperties());
        $identifierCondition = $this->conditionFactory->propertyHasValue($identifier, $idPropertyLink->getPath());
        $entities = $this->listEntities($type, [$identifierCondition]);
        if ($wrap) {
            $entities = $this->createArrayWrappers($entities, $type, 0);
        }

        switch (count($entities)) {
            case 0:
                throw AccessException::noEntityByIdentifier($type);
            case 1:
                return array_pop($entities);
            default:
                throw AccessException::multipleEntitiesByIdentifier($type);
        }
    }
}

