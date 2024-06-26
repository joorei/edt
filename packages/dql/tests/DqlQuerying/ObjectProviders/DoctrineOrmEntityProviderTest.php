<?php

declare(strict_types=1);

namespace Tests\DqlQuerying\ObjectProviders;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\Tools\Setup;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\MappingException;
use EDT\DqlQuerying\Contracts\OrderByInterface;
use EDT\DqlQuerying\Functions\AllEqual;
use EDT\DqlQuerying\Functions\AllTrue;
use EDT\DqlQuerying\Functions\Product;
use EDT\DqlQuerying\Functions\Property;
use EDT\DqlQuerying\Functions\Size;
use EDT\DqlQuerying\Functions\Sum;
use EDT\DqlQuerying\Functions\UpperCase;
use EDT\DqlQuerying\Functions\Value;
use EDT\DqlQuerying\ObjectProviders\DoctrineOrmEntityProvider;
use EDT\DqlQuerying\Utilities\JoinFinder;
use EDT\DqlQuerying\Utilities\QueryBuilderPreparer;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\JsonApi\RequestHandling\JsonApiSortingParser;
use EDT\JsonApi\Validation\SortValidator;
use EDT\Querying\ConditionParsers\Drupal\DrupalConditionParser;
use EDT\Querying\ConditionParsers\Drupal\DrupalFilterParser;
use EDT\Querying\ConditionParsers\Drupal\DrupalFilterValidator;
use EDT\Querying\ConditionParsers\Drupal\PredefinedDrupalConditionFactory;
use EDT\Querying\Contracts\PropertyPathAccessInterface;
use EDT\Querying\PropertyPaths\PropertyPath;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Tests\data\DqlModel\Book;
use Tests\data\DqlModel\Person;
use Tests\Querying\ConditionParsers\DrupalConditionFactoryTest;

class DoctrineOrmEntityProviderTest extends TestCase
{
    protected EntityManager $entityManager;

    protected DqlConditionFactory $conditionFactory;

    protected SortMethodFactory $sortingFactory;

    private QueryBuilderPreparer $personBuilderPreparer;

    /**
     * @var DoctrineOrmEntityProvider<ClauseFunctionInterface<bool>, OrderByInterface, Person>
     */
    private DoctrineOrmEntityProvider $personEntityProvider;

    /**
     * @var DoctrineOrmEntityProvider<ClauseFunctionInterface<bool>, OrderByInterface, Book>
     */
    private DoctrineOrmEntityProvider $bookEntityProvider;
    private JsonApiSortingParser $sortingTransformer;
    private SortValidator $sortingValidator;
    private DrupalFilterValidator $filterValidator;
    private DrupalFilterParser $filterTransformer;

    protected function setUp(): void
    {
        parent::setUp();
        $config = Setup::createAnnotationMetadataConfiguration(
            [__DIR__.'/tests/data/Model'],
            true,
            null,
            null,
            false
        );
        $paths = [__DIR__.'/tests/data/Model'];
        $driver = new AttributeDriver($paths);
        $config->setMetadataDriverImpl($driver);
        $conn = [
            'driver' => 'pdo_sqlite',
            'path' => __DIR__ . '/db.sqlite',
        ];
        $this->entityManager = EntityManager::create($conn, $config);
        $this->conditionFactory = new DqlConditionFactory();
        $this->sortingFactory = new SortMethodFactory();
        $metadataFactory = $this->entityManager->getMetadataFactory();
        $joinFinder = new JoinFinder($metadataFactory);
        $bookBuilderPreparer = new QueryBuilderPreparer(Book::class, $metadataFactory, $joinFinder);
        $this->personBuilderPreparer = new QueryBuilderPreparer(Person::class, $metadataFactory, $joinFinder);
        $predefinedDrupalConditionFactory = new PredefinedDrupalConditionFactory($this->conditionFactory);
        $validator = Validation::createValidator();
        $this->filterValidator = new DrupalFilterValidator($validator, $predefinedDrupalConditionFactory);
        $this->filterTransformer = new DrupalFilterParser(
            $this->conditionFactory,
            new DrupalConditionParser($predefinedDrupalConditionFactory),
            $this->filterValidator
        );
        $this->sortingTransformer = new JsonApiSortingParser($this->sortingFactory);
        $this->sortingValidator = new SortValidator($validator);
        $this->bookEntityProvider = new DoctrineOrmEntityProvider(
            $this->entityManager,
            $bookBuilderPreparer,
            Book::class
        );
        $this->personEntityProvider = new DoctrineOrmEntityProvider(
            $this->entityManager,
            $this->personBuilderPreparer,
            Book::class
        );
    }

    public function testTestsetup(): void
    {
        $metadata = $this->entityManager->getClassMetadata(Book::class);
        self::assertSame(Book::class, $metadata->name);
    }

    public function testAlwaysTrue(): void
    {
        $trueCondition = $this->conditionFactory->true();
        $queryBuilder = $this->bookEntityProvider->generateQueryBuilder([$trueCondition]);
        self::assertSame(
            /** @lang DQL */
            'SELECT Book FROM Tests\data\DqlModel\Book Book WHERE 1 = 1',
            $queryBuilder->getDQL()
        );
        self::assertCount(0, $queryBuilder->getParameters());
    }

    public function testAlwaysFalse(): void
    {
        $trueCondition = $this->conditionFactory->false();
        $queryBuilder = $this->bookEntityProvider->generateQueryBuilder([$trueCondition]);
        self::assertSame(
            /** @lang DQL */
            'SELECT Book FROM Tests\data\DqlModel\Book Book WHERE 1 = 2',
            $queryBuilder->getDQL()
        );
        self::assertCount(0, $queryBuilder->getParameters());
    }

    public function testAnyConditionApplies(): void
    {
        $emptyTitleCondition = $this->conditionFactory->propertyHasValue('', ['title']);
        $nullTitleCondition = $this->conditionFactory->propertyIsNull(['title']);
        $allConditionsApply = $this->conditionFactory->anyConditionApplies($emptyTitleCondition, $nullTitleCondition);
        $queryBuilder = $this->bookEntityProvider->generateQueryBuilder([$allConditionsApply]);
        self::assertSame(
            /** @lang DQL */
            'SELECT Book FROM Tests\data\DqlModel\Book Book WHERE Book.title = ?0 OR Book.title IS NULL',
            $queryBuilder->getDQL()
        );

        /** @var Collection<int, Parameter> $parameters */
        $parameters = $queryBuilder->getParameters();
        self::assertCount(1, $parameters);
        self::assertSame('', $parameters->first()->getValue());
    }

    public function testAllConditionsApply(): void
    {
        $bookA = $this->conditionFactory->propertyHasValue('Harry Potter and the Philosopher\'s Stone', ['books', 'title']);
        $bookB = $this->conditionFactory->propertyHasValue('Harry Potter and the Deathly Hallows', ['books', 'title']);
        $allConditionsApply = $this->conditionFactory->allConditionsApply($bookA, $bookB);
        $queryBuilder = $this->personEntityProvider->generateQueryBuilder([$allConditionsApply]);
        self::assertSame(
            /** @lang DQL */
            'SELECT Person FROM Tests\data\DqlModel\Person Person LEFT JOIN Person.books t_3e6230ca_Book WHERE t_3e6230ca_Book.title = ?0 AND t_3e6230ca_Book.title = ?1',
            $queryBuilder->getDQL()
        );

        /** @var Collection<int, Parameter> $parameters */
        $parameters = $queryBuilder->getParameters();
        self::assertCount(2, $parameters);
        self::assertSame('Harry Potter and the Philosopher\'s Stone', $parameters->first()->getValue());
        self::assertSame('Harry Potter and the Deathly Hallows', $parameters->last()->getValue());
    }

    public function testEqualsWithoutSorting(): void
    {
        $propertyHasValue = $this->conditionFactory->propertyHasValue('Example Street', ['author', 'birth', 'street']);
        $queryBuilder = $this->bookEntityProvider->generateQueryBuilder([$propertyHasValue]);
        self::assertSame(
            /** @lang DQL */
            'SELECT Book FROM Tests\data\DqlModel\Book Book LEFT JOIN Book.author t_58fb870d_Person LEFT JOIN t_58fb870d_Person.birth t_7e118c84_Birth WHERE t_7e118c84_Birth.street = ?0',
            $queryBuilder->getDQL()
        );

        /** @var Collection<int, Parameter> $parameters */
        $parameters = $queryBuilder->getParameters();
        self::assertCount(1, $parameters);
        self::assertSame('Example Street', $parameters->first()->getValue());
    }

    public function testEqualsWithAscendingFirstSorting(): void
    {
        $propertyHasValue = $this->conditionFactory->propertyHasValue('Example Street', ['author', 'birth', 'street']);
        $ascending = $this->sortingFactory->propertyAscending(['author', 'birth', 'street']);
        $descending = $this->sortingFactory->propertyDescending(['title']);
        $queryBuilder = $this->bookEntityProvider->generateQueryBuilder([$propertyHasValue], [$ascending, $descending]);
        self::assertSame(
            /** @lang DQL */
            'SELECT Book FROM Tests\data\DqlModel\Book Book LEFT JOIN Book.author t_58fb870d_Person LEFT JOIN t_58fb870d_Person.birth t_7e118c84_Birth WHERE t_7e118c84_Birth.street = ?0 ORDER BY t_7e118c84_Birth.street ASC, Book.title DESC',
            $queryBuilder->getDQL()
        );

        /** @var Collection<int, Parameter> $parameters */
        $parameters = $queryBuilder->getParameters();
        self::assertCount(1, $parameters);
        self::assertSame('Example Street', $parameters->first()->getValue());
    }

    public function testEqualsWithAscendingToManySorting(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage("Join processing failed for the path 'books.title' with the salt ''.");

        $propertyHasValue = $this->conditionFactory->propertyHasValue('Example Street', ['author', 'birth', 'street']);
        $ascending = $this->sortingFactory->propertyAscending(['books', 'title']);
        $this->bookEntityProvider->generateQueryBuilder([$propertyHasValue], [$ascending]);
    }

    public function testEqualsWithDescendingFirstSorting(): void
    {
        $propertyHasValue = $this->conditionFactory->propertyHasValue('Example Street', ['author', 'birth', 'street']);
        $descending = $this->sortingFactory->propertyDescending(['author', 'birth', 'street']);
        $ascending = $this->sortingFactory->propertyAscending(['title']);
        $queryBuilder = $this->bookEntityProvider->generateQueryBuilder([$propertyHasValue], [$descending, $ascending]);
        self::assertSame(
            /** @lang DQL */
            'SELECT Book FROM Tests\data\DqlModel\Book Book LEFT JOIN Book.author t_58fb870d_Person LEFT JOIN t_58fb870d_Person.birth t_7e118c84_Birth WHERE t_7e118c84_Birth.street = ?0 ORDER BY t_7e118c84_Birth.street DESC, Book.title ASC',
            $queryBuilder->getDQL()
        );

        /** @var Collection<int, Parameter> $parameters */
        $parameters = $queryBuilder->getParameters();
        self::assertCount(1, $parameters);
        self::assertSame('Example Street', $parameters->first()->getValue());
    }

    public function testEqualsWithAscendingFirstSortingAndPagination(): void
    {
        $propertyHasValue = $this->conditionFactory->propertyHasValue('Example Street', ['author', 'birth', 'street']);
        $ascending = $this->sortingFactory->propertyAscending(['author', 'birth', 'street']);
        $descending = $this->sortingFactory->propertyDescending(['title']);
        $queryBuilder = $this->bookEntityProvider->generateQueryBuilder(
            [$propertyHasValue],
            [$ascending, $descending],
            1,
            3
        );
        self::assertSame(
            /** @lang DQL */
            'SELECT Book FROM Tests\data\DqlModel\Book Book LEFT JOIN Book.author t_58fb870d_Person LEFT JOIN t_58fb870d_Person.birth t_7e118c84_Birth WHERE t_7e118c84_Birth.street = ?0 ORDER BY t_7e118c84_Birth.street ASC, Book.title DESC',
            $queryBuilder->getDQL()
        );

        /** @var Collection<int, Parameter> $parameters */
        $parameters = $queryBuilder->getParameters();
        self::assertCount(1, $parameters);
        self::assertSame('Example Street', $parameters->first()->getValue());
        self::assertSame(1, $queryBuilder->getFirstResult());
        self::assertSame(3, $queryBuilder->getMaxResults());
    }

    public function testNullRelationship(): void
    {
        $propertyIsNull = $this->conditionFactory->propertyIsNull(['author', 'birth']);
        $queryBuilder = $this->bookEntityProvider->generateQueryBuilder([$propertyIsNull]);
        self::assertSame(
            /** @lang DQL */
            'SELECT Book FROM Tests\data\DqlModel\Book Book LEFT JOIN Book.author t_58fb870d_Person LEFT JOIN t_58fb870d_Person.birth t_7e118c84_Birth WHERE t_7e118c84_Birth IS NULL',
            $queryBuilder->getDQL()
        );
        self::assertCount(0, $queryBuilder->getParameters());
    }

    public function testNullNonRelationship(): void
    {
        $propertyIsNull = $this->conditionFactory->propertyIsNull(['author', 'birth', 'street']);
        $queryBuilder = $this->bookEntityProvider->generateQueryBuilder([$propertyIsNull]);
        self::assertSame(
            /** @lang DQL */
            'SELECT Book FROM Tests\data\DqlModel\Book Book LEFT JOIN Book.author t_58fb870d_Person LEFT JOIN t_58fb870d_Person.birth t_7e118c84_Birth WHERE t_7e118c84_Birth.street IS NULL',
            $queryBuilder->getDQL()
        );
        self::assertCount(0, $queryBuilder->getParameters());
    }

    public function testEmptyRelationship(): void
    {
        $propertyHasSize = $this->conditionFactory->propertyHasSize(0, ['author']);
        $queryBuilder = $this->bookEntityProvider->generateQueryBuilder([$propertyHasSize]);
        self::assertSame(
            /** @lang DQL */
            'SELECT Book FROM Tests\data\DqlModel\Book Book WHERE SIZE(Book.author) = ?0',
            $queryBuilder->getDQL()
        );

        /** @var Collection<int, Parameter> $parameters */
        $parameters = $queryBuilder->getParameters();
        self::assertCount(1, $parameters);
        self::assertSame(0, $parameters->first()->getValue());
    }

    public function testNonEmptyRelationship(): void
    {
        $propertyHasNotSize = $this->conditionFactory->propertyHasNotSize(0, ['books']);
        $queryBuilder = $this->personEntityProvider->generateQueryBuilder([$propertyHasNotSize]);
        self::assertSame(
            /** @lang DQL */
            'SELECT Person FROM Tests\data\DqlModel\Person Person WHERE NOT(SIZE(Person.books) = ?0)',
            $queryBuilder->getDQL()
        );

        /** @var Collection<int, Parameter> $parameters */
        $parameters = $queryBuilder->getParameters();
        self::assertCount(1, $parameters);
        self::assertSame(0, $parameters->first()->getValue());
    }

    public function testNonEmptyRelationshipNested(): void
    {
        $propertyHasNotSize = $this->conditionFactory->propertyHasNotSize(0, ['author', 'books']);
        $queryBuilder = $this->bookEntityProvider->generateQueryBuilder([$propertyHasNotSize]);
        self::assertSame(
            /** @lang DQL */
            'SELECT Book FROM Tests\data\DqlModel\Book Book LEFT JOIN Book.author t_58fb870d_Person WHERE NOT(SIZE(t_58fb870d_Person.books) = ?0)',
            $queryBuilder->getDQL()
        );

        /** @var Collection<int, Parameter> $parameters */
        $parameters = $queryBuilder->getParameters();
        self::assertCount(1, $parameters);
        self::assertSame(0, $parameters->first()->getValue());
    }

    public function testBetweenValues(): void
    {
        $propertyBetween = $this->conditionFactory->propertyBetweenValuesInclusive(-1, 5, ['author', 'birth', 'streetNumber']);
        $queryBuilder = $this->bookEntityProvider->generateQueryBuilder([$propertyBetween]);
        self::assertSame(
            /** @lang DQL */
            'SELECT Book FROM Tests\data\DqlModel\Book Book LEFT JOIN Book.author t_58fb870d_Person LEFT JOIN t_58fb870d_Person.birth t_7e118c84_Birth WHERE t_7e118c84_Birth.streetNumber BETWEEN ?0 AND ?1',
            $queryBuilder->getDQL()
        );

        /** @var Collection<int, Parameter> $parameters */
        $parameters = $queryBuilder->getParameters();
        self::assertCount(2, $parameters);
        self::assertSame(-1, $parameters->first()->getValue());
        self::assertSame(5, $parameters->last()->getValue());
    }

    public function testContainsValueCaseInsensitive(): void
    {
        $containsValue = $this->conditionFactory->propertyHasStringContainingCaseInsensitiveValue('Ave', ['author', 'birth', 'street']);
        $queryBuilder = $this->bookEntityProvider->generateQueryBuilder([$containsValue]);
        self::assertSame(
            /** @lang DQL */
            "SELECT Book FROM Tests\data\DqlModel\Book Book LEFT JOIN Book.author t_58fb870d_Person LEFT JOIN t_58fb870d_Person.birth t_7e118c84_Birth WHERE LOWER(t_7e118c84_Birth.street) LIKE CONCAT('%', LOWER(?0), '%')",
            $queryBuilder->getDQL()
        );

        /** @var Collection<int, Parameter> $parameters */
        $parameters = $queryBuilder->getParameters();
        self::assertCount(1, $queryBuilder->getParameters());
        self::assertSame('Ave', $parameters->first()->getValue());
    }

    public function testOneOfValues(): void
    {
        $containsValue = $this->conditionFactory->propertyHasAnyOfValues([1, 2, 3], ['author', 'birth', 'streetNumber']);
        $queryBuilder = $this->bookEntityProvider->generateQueryBuilder([$containsValue]);
        self::assertSame(
            /** @lang DQL */
            'SELECT Book FROM Tests\data\DqlModel\Book Book LEFT JOIN Book.author t_58fb870d_Person LEFT JOIN t_58fb870d_Person.birth t_7e118c84_Birth WHERE t_7e118c84_Birth.streetNumber IN(?0)',
            $queryBuilder->getDQL()
        );

        /** @var Collection<int, Parameter> $parameters */
        $parameters = $queryBuilder->getParameters();
        self::assertCount(1, $parameters);
        self::assertSame([1, 2, 3], $parameters->first()->getValue());
    }

    public function testOneOfValuesWithEmptyArray(): void
    {
        $containsValue = $this->conditionFactory->propertyHasAnyOfValues([], ['author', 'birth', 'streetNumber']);
        $queryBuilder = $this->bookEntityProvider->generateQueryBuilder([$containsValue]);
        self::assertSame(
            /** @lang DQL */
            'SELECT Book FROM Tests\data\DqlModel\Book Book WHERE 1 = 2',
            $queryBuilder->getDQL()
        );
        self::assertCount(0, $queryBuilder->getParameters());
    }

    public function testNotOneOfValuesWithEmptyArray(): void
    {
        $containsValue = $this->conditionFactory->propertyHasNotAnyOfValues([], ['author', 'birth', 'streetNumber']);
        $queryBuilder = $this->bookEntityProvider->generateQueryBuilder([$containsValue]);
        self::assertSame(
            /** @lang DQL */
            'SELECT Book FROM Tests\data\DqlModel\Book Book WHERE NOT(1 = 2)',
            $queryBuilder->getDQL()
        );
        self::assertCount(0, $queryBuilder->getParameters());
    }

    public function testPropertyHasStringAsMember(): void
    {
        $novelBook = $this->conditionFactory->propertyHasStringAsMember('Novel', ['tags']);
        $queryBuilder = $this->bookEntityProvider->generateQueryBuilder([$novelBook]);
        self::assertSame(
            /** @lang DQL */
            'SELECT Book FROM Tests\data\DqlModel\Book Book WHERE ?0 MEMBER OF Book.tags',
            $queryBuilder->getDQL()
        );

        /** @var Collection<int, Parameter> $parameters */
        $parameters = $queryBuilder->getParameters();
        self::assertCount(1, $parameters);
        self::assertSame('Novel', $parameters->first()->getValue());
    }

    public function testPropertyHasNotStringAsMember(): void
    {
        $noNovelBook = $this->conditionFactory->propertyHasNotStringAsMember('Novel', ['tags']);
        $queryBuilder = $this->bookEntityProvider->generateQueryBuilder([$noNovelBook]);
        self::assertSame(
            /** @lang DQL */
            'SELECT Book FROM Tests\data\DqlModel\Book Book WHERE NOT(?0 MEMBER OF Book.tags)',
            $queryBuilder->getDQL()
        );

        /** @var Collection<int, Parameter> $parameters */
        $parameters = $queryBuilder->getParameters();
        self::assertCount(1, $parameters);
        self::assertSame('Novel', $parameters->first()->getValue());
    }

    public function testPropertiesEqual(): void
    {
        $birthDateCondition = $this->conditionFactory->propertiesEqual(['author', 'birth', 'month'], ['author', 'birth', 'day']);
        $queryBuilder = $this->bookEntityProvider->generateQueryBuilder([$birthDateCondition]);
        self::assertSame(
            /** @lang DQL */
            'SELECT Book FROM Tests\data\DqlModel\Book Book LEFT JOIN Book.author t_58fb870d_Person LEFT JOIN t_58fb870d_Person.birth t_7e118c84_Birth WHERE t_7e118c84_Birth.month = t_7e118c84_Birth.day',
            $queryBuilder->getDQL()
        );
        self::assertCount(0, $queryBuilder->getParameters());
    }

    public function testPropertiesEqualWithForeignEntityClass(): void
    {
        $birthDateCondition = $this->conditionFactory->propertiesEqual(
            ['author', 'birth', 'month'],
            ['author', 'birth', 'day'],
            Book::class
        );
        $queryBuilder = $this->bookEntityProvider->generateQueryBuilder([$birthDateCondition]);
        self::assertSame(
            /** @lang DQL */
            'SELECT Book FROM Tests\data\DqlModel\Book Book LEFT JOIN Book.author t_58fb870d_Person LEFT JOIN t_58fb870d_Person.birth t_7e118c84_Birth, Tests\data\DqlModel\Book t__Book LEFT JOIN t__Book.author t_71115441_Person LEFT JOIN t_71115441_Person.birth t_1a171a0d_Birth WHERE t_7e118c84_Birth.month = t_1a171a0d_Birth.day',
            $queryBuilder->getDQL()
        );
        self::assertCount(0, $queryBuilder->getParameters());
    }

    public function testUpperCase(): void
    {
        $propertyPath = new PropertyPath(null, '', PropertyPathAccessInterface::UNPACK, ['title']);
        $sameUpperCase = new AllEqual(
            new UpperCase(new Property($propertyPath)),
            new Value('FOO')
        );
        $queryBuilder = $this->bookEntityProvider->generateQueryBuilder([$sameUpperCase]);
        self::assertSame(
            /** @lang DQL */
            'SELECT Book FROM Tests\data\DqlModel\Book Book WHERE UPPER(Book.title) = ?0',
            $queryBuilder->getDQL()
        );

        /** @var Collection<int, Parameter> $parameters */
        $parameters = $queryBuilder->getParameters();
        self::assertCount(1, $parameters);
        self::assertSame('FOO', $parameters->first()->getValue());
    }

    public function testSum(): void
    {
        $propertyPathInstance = new PropertyPath(null, '', PropertyPathAccessInterface::UNPACK, ['name']);
        $size = new Size(new Property($propertyPathInstance));
        $sum = new AllEqual(
            new Sum($size, $size),
            new Value(4)
        );
        $queryBuilder = $this->personEntityProvider->generateQueryBuilder([$sum]);
        self::assertSame(
            /** @lang DQL */
            'SELECT Person FROM Tests\data\DqlModel\Person Person WHERE SIZE(Person.name) + SIZE(Person.name) = ?0',
            $queryBuilder->getDQL()
        );

        /** @var Collection<int, Parameter> $parameters */
        $parameters = $queryBuilder->getParameters();
        self::assertCount(1, $parameters);
        self::assertSame(4, $parameters->first()->getValue());
    }

    public function testSumAdditionalAddends(): void
    {
        $propertyPathInstance = new PropertyPath(null, '', PropertyPathAccessInterface::UNPACK, ['name']);
        $size = new Size(new Property($propertyPathInstance));
        $sum = new AllEqual(
            new Sum($size, $size, $size, $size),
            new Value(8)
        );
        $queryBuilder = $this->personEntityProvider->generateQueryBuilder([$sum]);
        self::assertSame(
            /** @lang DQL */
            'SELECT Person FROM Tests\data\DqlModel\Person Person WHERE ((SIZE(Person.name) + SIZE(Person.name)) + SIZE(Person.name)) + SIZE(Person.name) = ?0',
            $queryBuilder->getDQL()
        );

        /** @var Collection<int, Parameter> $parameters */
        $parameters = $queryBuilder->getParameters();
        self::assertCount(1, $parameters);
        self::assertSame(8, $parameters->first()->getValue());
    }

    public function testSumPowMixed(): void
    {
        $propertyPathInstance = new PropertyPath(null, '', PropertyPathAccessInterface::UNPACK, ['name']);
        $size = new Size(new Property($propertyPathInstance));
        $sum = new AllEqual(
            new Product(
                new Product(new Sum($size, $size), new Value(2)),
                new Sum($size, $size)
            ),
            new Sum(
                new Value(8),
                new Product(new Sum($size, $size), new Value(2)),
                new Value(8)
            )
        );
        $queryBuilder = $this->personEntityProvider->generateQueryBuilder([$sum]);
        self::assertSame(
            /** @lang DQL */
            'SELECT Person FROM Tests\data\DqlModel\Person Person WHERE ((SIZE(Person.name) + SIZE(Person.name)) * ?0) * (SIZE(Person.name) + SIZE(Person.name)) = (?1 + ((SIZE(Person.name) + SIZE(Person.name)) * ?2)) + ?3',
            $queryBuilder->getDQL()
        );

        /** @var Collection<int, Parameter> $parameters */
        $parameters = $queryBuilder->getParameters();
        self::assertCount(4, $queryBuilder->getParameters());
        self::assertSame(2, $parameters->first()->getValue());
        self::assertSame(8, $parameters->next()->getValue());
        self::assertSame(2, $parameters->next()->getValue());
        self::assertSame(8, $parameters->next()->getValue());
    }

    public function testCustomMemberCondition(): void
    {
        $propertyPath = new PropertyPath(null, '', PropertyPathAccessInterface::DIRECT, ['books', 'title']);
        $condition = new AllTrue(
            new AllEqual(
                new Value('Harry Potter and the Philosopher\'s Stone'),
                new Property($propertyPath)
            ),
            new AllEqual(
                new Value('Harry Potter and the Deathly Hallows'),
                new Property($propertyPath)
            )
        );
        $queryBuilder = $this->personEntityProvider->generateQueryBuilder([$condition]);
        self::assertSame(
            /** @lang DQL */
            'SELECT Person FROM Tests\data\DqlModel\Person Person LEFT JOIN Person.books t_3e6230ca_Book WHERE ?0 = t_3e6230ca_Book.title AND ?1 = t_3e6230ca_Book.title',
            $queryBuilder->getDQL()
        );

        /** @var Collection<int, Parameter> $parameters */
        $parameters = $queryBuilder->getParameters();
        self::assertCount(2, $queryBuilder->getParameters());
        self::assertSame("Harry Potter and the Philosopher's Stone", $parameters->first()->getValue());
        self::assertSame('Harry Potter and the Deathly Hallows', $parameters->next()->getValue());
    }

    public function testCustomMemberConditionWithSalt(): void
    {
        $propertyPathA = new PropertyPath(null, 'a', PropertyPathAccessInterface::DIRECT, ['books', 'title']);
        $propertyPathB = new PropertyPath(null, 'b', PropertyPathAccessInterface::DIRECT, ['books', 'title']);
        $condition = new AllTrue(
            new AllEqual(
                new Property($propertyPathA),
                new Value('Harry Potter and the Philosopher\'s Stone')
            ),
            new AllEqual(
                new Property($propertyPathB),
                new Value('Harry Potter and the Deathly Hallows')
            )
        );
        $queryBuilder = $this->personEntityProvider->generateQueryBuilder([$condition]);
        self::assertSame(
            /** @lang DQL */
            'SELECT Person FROM Tests\data\DqlModel\Person Person LEFT JOIN Person.books t_99a6b3fc_Book LEFT JOIN Person.books t_246cdf32_Book WHERE t_99a6b3fc_Book.title = ?0 AND t_246cdf32_Book.title = ?1',
            $queryBuilder->getDQL()
        );

        /** @var Collection<int, Parameter> $parameters */
        $parameters = $queryBuilder->getParameters();
        self::assertCount(2, $parameters);
        self::assertSame("Harry Potter and the Philosopher's Stone", $parameters->first()->getValue());
        self::assertSame('Harry Potter and the Deathly Hallows', $parameters->next()->getValue());
    }

    public function testAllValuesPresentInMemberListProperties(): void
    {
        $condition = $this->conditionFactory->allValuesPresentInMemberListProperties([
            'Harry Potter and the Philosopher\'s Stone',
            'Harry Potter and the Deathly Hallows'
        ], ['books', 'title']);
        $queryBuilder = $this->personEntityProvider->generateQueryBuilder([$condition]);
        self::assertSame(
            /** @lang DQL */
            'SELECT Person FROM Tests\data\DqlModel\Person Person LEFT JOIN Person.books t_4dba5d08_Book LEFT JOIN Person.books t_902c848d_Book WHERE t_4dba5d08_Book.title = ?0 AND t_902c848d_Book.title = ?1',
            $queryBuilder->getDQL()
        );

        /** @var Collection<int, Parameter> $parameters */
        $parameters = $queryBuilder->getParameters();
        self::assertCount(2, $parameters);
        self::assertSame("Harry Potter and the Philosopher's Stone", $parameters->first()->getValue());
        self::assertSame('Harry Potter and the Deathly Hallows', $parameters->next()->getValue());
    }

    public function testAllValuesPresentInMemberListPropertiesWithSpecificSelect(): void
    {
        $birthDayPath = new PropertyPath(null, '', PropertyPath::DIRECT, ['birth', 'day']);
        $birthMonthPath = new PropertyPath(null, '', PropertyPath::DIRECT, ['birth', 'month']);
        $birthYearPath = new PropertyPath(null, '', PropertyPath::DIRECT, ['birth', 'year']);

        $selectBirthSum = new Sum(...array_map(
            static fn (PropertyPath $propertyPath): Property => new Property($propertyPath),
            [$birthDayPath, $birthMonthPath, $birthYearPath]
        ));
        $titlePath = new PropertyPath(null, '0', PropertyPath::DIRECT, ['books', 'title']);
        $selectTitleProperty = new Property($titlePath);
        $namePath = new PropertyPath(null, '0', PropertyPath::DIRECT, ['name']);
        $selectNameProperty = new Property($namePath);

        $condition = $this->conditionFactory->allValuesPresentInMemberListProperties([
            'Harry Potter and the Philosopher\'s Stone',
            'Harry Potter and the Deathly Hallows'
        ], ['books', 'title']);

        $this->personBuilderPreparer->setSelectExpressions([
            'name' => $selectNameProperty,
            'birthSum' => $selectBirthSum,
            'title' => $selectTitleProperty
        ]);
        $queryBuilder = $this->personEntityProvider->generateQueryBuilder([$condition], []);
        self::assertSame(
            /** @lang DQL */
            'SELECT Person.name AS name, (t_48c89847_Birth.day + t_48c89847_Birth.month) + t_48c89847_Birth.year AS birthSum, t_4dba5d08_Book.title AS title FROM Tests\data\DqlModel\Person Person LEFT JOIN Person.birth t_48c89847_Birth LEFT JOIN Person.books t_4dba5d08_Book LEFT JOIN Person.books t_902c848d_Book WHERE t_4dba5d08_Book.title = ?0 AND t_902c848d_Book.title = ?1',
            $queryBuilder->getDQL()
        );

        /** @var Collection<int, Parameter> $parameters */
        $parameters = $queryBuilder->getParameters();
        self::assertCount(2, $parameters);
        self::assertSame("Harry Potter and the Philosopher's Stone", $parameters->first()->getValue());
        self::assertSame('Harry Potter and the Deathly Hallows', $parameters->next()->getValue());
    }
}
