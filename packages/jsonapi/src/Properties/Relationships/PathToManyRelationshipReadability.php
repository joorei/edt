<?php

declare(strict_types=1);

namespace EDT\JsonApi\Properties\Relationships;

use EDT\Querying\Contracts\PathsBasedInterface;
use EDT\Querying\Contracts\PropertyAccessorInterface;
use EDT\Wrapping\Contracts\Types\TransferableTypeInterface;
use EDT\Wrapping\Properties\EntityVerificationTrait;
use EDT\Wrapping\Properties\ToManyRelationshipReadabilityInterface;

/**
 * @template TCondition of PathsBasedInterface
 * @template TSorting of PathsBasedInterface
 * @template TEntity of object
 * @template TRelationship of object
 *
 * @template-implements ToManyRelationshipReadabilityInterface<TCondition, TSorting, TEntity, TRelationship>>
 */
class PathToManyRelationshipReadability implements ToManyRelationshipReadabilityInterface
{
    use EntityVerificationTrait;
    /**
     * @param class-string<TEntity> $entityClass
     * @param non-empty-list<non-empty-string> $propertyPath
     * @param TransferableTypeInterface<TCondition, TSorting, TRelationship> $relationshipType
     */
    public function __construct(
        protected readonly string $entityClass,
        protected readonly array $propertyPath,
        protected readonly bool $defaultField,
        protected readonly bool $defaultInclude,
        protected readonly TransferableTypeInterface $relationshipType,
        protected readonly PropertyAccessorInterface $propertyAccessor
    ) {}

    public function isDefaultInclude(): bool
    {
        return $this->defaultInclude;
    }

    public function getRelationshipType(): TransferableTypeInterface
    {
        return $this->relationshipType;
    }

    public function isDefaultField(): bool
    {
        return $this->defaultField;
    }

    public function getValue(object $entity, array $conditions, array $sortMethods): array
    {
        $relationshipEntities = $this->propertyAccessor->getValueByPropertyPath($entity, ...$this->propertyPath);
        $relationshipClass = $this->relationshipType->getEntityClass();
        $relationshipEntities = $this->assertValidToManyValue($relationshipEntities, $relationshipClass);

        return $this->relationshipType->reindexEntities($relationshipEntities, $conditions, $sortMethods);
    }
}