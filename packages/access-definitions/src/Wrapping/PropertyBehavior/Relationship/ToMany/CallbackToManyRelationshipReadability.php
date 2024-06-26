<?php

declare(strict_types=1);

namespace EDT\Wrapping\PropertyBehavior\Relationship\ToMany;

use EDT\Wrapping\Contracts\TransferableTypeProviderInterface;
use EDT\Wrapping\Contracts\Types\TransferableTypeInterface;
use EDT\Wrapping\PropertyBehavior\EntityVerificationTrait;

/**
 * @template TEntity of object
 * @template TRelationship of object
 *
 * @template-implements ToManyRelationshipReadabilityInterface<TEntity, TRelationship>>
 */
class CallbackToManyRelationshipReadability implements ToManyRelationshipReadabilityInterface
{
    use EntityVerificationTrait;

    /**
     * @param callable(TEntity): iterable<TRelationship> $readCallback
     * @param TransferableTypeInterface<TRelationship>|TransferableTypeProviderInterface<TRelationship> $relationshipType
     */
    public function __construct(
        protected readonly bool                                                        $defaultField,
        protected readonly bool                                                        $defaultInclude,
        protected readonly mixed                                                       $readCallback,
        protected readonly TransferableTypeInterface|TransferableTypeProviderInterface $relationshipType,
    ) {}

    public function isDefaultInclude(): bool
    {
        return $this->defaultInclude;
    }

    public function getRelationshipType(): TransferableTypeInterface
    {
        return $this->relationshipType instanceof TransferableTypeInterface
            ? $this->relationshipType
            : $this->relationshipType->getType();
    }

    public function isDefaultField(): bool
    {
        return $this->defaultField;
    }

    public function getValue(object $entity, array $conditions, array $sortMethods): array
    {
        $relationshipEntities = ($this->readCallback)($entity);
        $relationshipClass = $this->getRelationshipType()->getEntityClass();
        $relationshipEntities = $this->assertValidToManyValue($relationshipEntities, $relationshipClass);

        return $this->getRelationshipType()->reindexEntities($relationshipEntities, $conditions, $sortMethods);
    }
}
