<?php

declare(strict_types=1);

namespace Tests\data\Types;

use EDT\ConditionFactory\ConditionFactoryInterface;
use EDT\JsonApi\ApiDocumentation\AttributeTypeResolver;
use EDT\JsonApi\ApiDocumentation\OptionalField;
use EDT\JsonApi\InputHandling\PhpEntityRepository;
use EDT\JsonApi\InputHandling\RepositoryInterface;
use EDT\JsonApi\RequestHandling\ExpectedPropertyCollection;
use EDT\JsonApi\RequestHandling\ModifiedEntity;
use EDT\Querying\Contracts\PropertyAccessorInterface;
use EDT\Querying\PropertyAccessors\ReflectionPropertyAccessor;
use EDT\Querying\PropertyPaths\NonRelationshipLink;
use EDT\Querying\PropertyPaths\RelationshipLink;
use EDT\Wrapping\Contracts\TypeProviderInterface;
use EDT\Wrapping\Contracts\Types\FilteringTypeInterface;
use EDT\Wrapping\Contracts\Types\SortingTypeInterface;
use EDT\Wrapping\Contracts\Types\TransferableTypeInterface;
use EDT\Wrapping\EntityDataInterface;
use EDT\Wrapping\PropertyBehavior\Identifier\PathIdentifierReadability;
use EDT\Wrapping\PropertyBehavior\Relationship\ToMany\PathToManyRelationshipReadability;
use EDT\Wrapping\PropertyBehavior\Relationship\ToMany\PathToManyRelationshipSetBehavior;
use EDT\Wrapping\ResourceBehavior\ResourceReadability;
use EDT\Wrapping\ResourceBehavior\ResourceUpdatability;
use Symfony\Component\Validator\Validation;
use Tests\data\AdModel\Person;
use Webmozart\Assert\Assert;

class AuthorType implements
    TransferableTypeInterface,
    FilteringTypeInterface,
    SortingTypeInterface
{
    protected readonly RepositoryInterface $repository;

    public function __construct(
        protected readonly ConditionFactoryInterface $conditionFactory,
        protected readonly TypeProviderInterface $typeProvider,
        protected readonly PropertyAccessorInterface $propertyAccessor,
        protected readonly AttributeTypeResolver $typeResolver
    ) {
        $this->repository = PhpEntityRepository::createDefault(Validation::createValidator(), new ReflectionPropertyAccessor(), []);
    }

    public function getReadability(): ResourceReadability
    {
        return new ResourceReadability(
            [
                'name' => new TestAttributeReadability(['name'], $this->propertyAccessor),
                'pseudonym' => new TestAttributeReadability(['pseudonym'], $this->propertyAccessor),
                'birthCountry' => new TestAttributeReadability(['birth', 'country'], $this->propertyAccessor),
            ],
            [],
            [
                'books' => new PathToManyRelationshipReadability(
                    $this->getEntityClass(),
                    ['books'],
                    false,
                    false,
                    $this->typeProvider->getTypeByIdentifier(BookType::class),
                    $this->propertyAccessor
                ),
            ],
            new PathIdentifierReadability(
                $this->getEntityClass(),
                ['id'],
                $this->propertyAccessor,
            )
        );
    }

    public function getFilteringProperties(): array
    {
        return [
            'id' => new NonRelationshipLink(['id']),
            'name' => new NonRelationshipLink(['name']),
            'pseudonym' => new NonRelationshipLink(['pseudonym']),
            'books' => new RelationshipLink(
                ['books'],
                fn () => $this->typeProvider
                    ->getTypeByIdentifier(BookType::class)
                    ->getFilteringProperties()
            ),
            'birthCountry' => new NonRelationshipLink(['birth', 'country']),
        ];
    }

    public function getSortingProperties(): array
    {
        return [
            'name' => new NonRelationshipLink(['name']),
            'pseudonym' => new NonRelationshipLink(['pseudonym']),
            'birthCountry' => new NonRelationshipLink(['birth', 'country']),
        ];
    }

    public function getAccessConditions(): array
    {
        return [$this->conditionFactory->propertyHasNotSize(0, ['books'])];
    }

    public function getEntityClass(): string
    {
        return Person::class;
    }

    public function getUpdatability(): ResourceUpdatability
    {
        $bookType = $this->typeProvider->getTypeByIdentifier(BookType::class);

        return new ResourceUpdatability(
            [
                'name' => [new TestAttributeSetBehavior(
                    'name',
                    ['name'],
                    $this->propertyAccessor,
                    OptionalField::YES
                )],
                'birthCountry' => [new TestAttributeSetBehavior(
                    'birthCountry',
                    ['birth', 'country'],
                    $this->propertyAccessor,
                    OptionalField::YES
                )],
            ],
            [],
            [
                'books' => [new PathToManyRelationshipSetBehavior(
                    'books',
                    self::class,
                    [],
                    $bookType->getAccessConditions(),
                    $bookType,
                    ['books'],
                    $this->propertyAccessor,
                    OptionalField::YES
                )],
            ],
            []
        );
    }

    public function getTypeName(): string
    {
        return self::class;
    }

    public function getEntitiesForRelationship(array $identifiers, array $conditions, array $sortMethods): array
    {
        throw new \RuntimeException();
    }

    public function getExpectedUpdateProperties(): ExpectedPropertyCollection
    {
        throw new \RuntimeException();
    }

    public function updateEntity(string $entityId, EntityDataInterface $entityData): ModifiedEntity
    {
        throw new \RuntimeException();
    }

    public function assertMatchingEntity(object $entity, array $conditions): void
    {
        $conditions = array_merge($conditions, $this->getAccessConditions());
        Assert::true($this->repository->isMatchingEntity($entity, $conditions));
    }

    public function isMatchingEntity(object $entity, array $conditions): bool
    {
        $conditions = array_merge($conditions, $this->getAccessConditions());

        return $this->repository->isMatchingEntity($entity, $conditions);
    }

    public function reindexEntities(array $entities, array $conditions, array $sortMethods): array
    {
        return $this->repository->reindexEntities($entities, $conditions, $sortMethods);
    }

    public function getEntityForRelationship(string $identifier, array $conditions): object
    {
        throw new \RuntimeException();
    }
}
