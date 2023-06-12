<?php

declare(strict_types=1);

namespace EDT\JsonApi\Properties\Relationships;

use EDT\Querying\Contracts\PathsBasedInterface;
use EDT\Querying\Contracts\PropertyAccessorInterface;
use EDT\Wrapping\Contracts\Types\TransferableTypeInterface;
use EDT\Wrapping\Properties\EntityVerificationTrait;
use EDT\Wrapping\Properties\ToOneRelationshipReadabilityInterface;
use EDT\Wrapping\Utilities\EntityVerifierInterface;

/**
 * @template TCondition of PathsBasedInterface
 * @template TSorting of PathsBasedInterface
 * @template TEntity of object
 * @template TRelationship of object
 *
 * @template-implements ToOneRelationshipReadabilityInterface<TCondition, TSorting, TEntity, TRelationship>
 */
class PathToOneRelationshipReadability implements ToOneRelationshipReadabilityInterface
{
    use EntityVerificationTrait;

    /**
     * @param class-string<TEntity> $entityClass
     * @param non-empty-list<non-empty-string> $propertyPath
     * @param TransferableTypeInterface<TCondition, TSorting, TRelationship> $relationshipType
     * @param EntityVerifierInterface<TCondition, TSorting> $entityVerifier
     */
    public function __construct(
        protected readonly string $entityClass,
        protected readonly array $propertyPath,
        protected readonly bool $defaultField,
        protected readonly bool $defaultInclude,
        protected readonly TransferableTypeInterface $relationshipType,
        protected readonly PropertyAccessorInterface $propertyAccessor,
        protected readonly EntityVerifierInterface $entityVerifier
    ) {}

    public function getValue(object $entity, array $conditions): ?object
    {
        $relationship = $this->propertyAccessor->getValueByPropertyPath($entity, ...$this->propertyPath);
        $relationshipClass = $this->relationshipType->getEntityClass();
        $relationship = $this->assertValidToOneValue($relationship, $relationshipClass);
        $relationship = $this->entityVerifier->filterEntity($relationship, $conditions, $this->relationshipType);

        return $relationship;
    }

    public function isDefaultField(): bool
    {
        return $this->defaultField;
    }

    public function getRelationshipType(): TransferableTypeInterface
    {
        return $this->relationshipType;
    }

    public function isDefaultInclude(): bool
    {
        return $this->defaultInclude;
    }
}
