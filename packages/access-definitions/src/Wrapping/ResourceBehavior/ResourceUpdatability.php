<?php

declare(strict_types=1);

namespace EDT\Wrapping\ResourceBehavior;

use EDT\ConditionFactory\DrupalFilterInterface;
use EDT\Querying\Contracts\EntityBasedInterface;
use EDT\Wrapping\Contracts\Types\NamedTypeInterface;
use EDT\Wrapping\Contracts\Types\PropertyReadableTypeInterface;
use EDT\Wrapping\EntityDataInterface;
use EDT\Wrapping\PropertyBehavior\PropertySetBehaviorInterface;
use EDT\Wrapping\PropertyBehavior\PropertyUpdatabilityInterface;
use EDT\Wrapping\PropertyBehavior\Relationship\RelationshipSetBehaviorInterface;
use InvalidArgumentException;
use Webmozart\Assert\Assert;
use function array_key_exists;

/**
 * @template TEntity of object
 */
class ResourceUpdatability extends AbstractResourceModifier
{
    /**
     * @param array<non-empty-string, list<PropertyUpdatabilityInterface<TEntity>>> $attributes
     * @param array<non-empty-string, list<RelationshipSetBehaviorInterface<TEntity, object>>> $toOneRelationships
     * @param array<non-empty-string, list<RelationshipSetBehaviorInterface<TEntity, object>>> $toManyRelationships
     * @param list<PropertyUpdatabilityInterface<TEntity>> $generalUpdateBehaviors
     */
    public function __construct(
        protected readonly array $attributes,
        protected readonly array $toOneRelationships,
        protected readonly array $toManyRelationships,
        protected readonly array $generalUpdateBehaviors
    ) {
        Assert::isEmpty(array_intersect_key($this->attributes, $this->toOneRelationships));
        Assert::isEmpty(array_intersect_key($this->attributes, $this->toManyRelationships));
        Assert::isEmpty(array_intersect_key($this->toOneRelationships, $this->toManyRelationships));
    }

    /**
     * Get all setabilities, that correspond to the given entity data.
     *
     * @return list<PropertyUpdatabilityInterface<TEntity>>
     */
    protected function getSetabilities(): array
    {
        return array_merge(
            $this->generalUpdateBehaviors,
            array_merge(...array_values($this->attributes)),
            array_merge(...array_values($this->toOneRelationships)),
            array_merge(...array_values($this->toManyRelationships))
        );
    }

    /**
     * @return list<non-empty-string>
     */
    public function getAttributeNames(): array
    {
        return array_keys($this->attributes);
    }

    /**
     * @return list<non-empty-string>
     */
    public function getToOneRelationshipNames(): array
    {
        return array_keys($this->toOneRelationships);
    }

    /**
     * @return list<non-empty-string>
     */
    public function getToManyRelationshipNames(): array
    {
        return array_keys($this->toManyRelationships);
    }

    /**
     * @param TEntity $entity
     *
     * @return list<non-empty-string>
     */
    public function updateProperties(object $entity, EntityDataInterface $entityData): array
    {
        $setabilities = $this->getSetabilities();

        return $this->getSetabilitiesRequestDeviations($setabilities, $entity, $entityData);
    }

    /**
     * Merges all entity conditions of the setabilities, that correspond to the given entity data.
     *
     * Does not process any paths, as the set-behavior entity conditions are expected to
     * be hardcoded and not supplied via request.
     *
     * @return list<DrupalFilterInterface>
     */
    public function getEntityConditions(EntityDataInterface $entityData): array
    {
        $entityConditions = array_map(
            static fn (PropertySetBehaviorInterface $accessibility): array => $accessibility->getEntityConditions($entityData),
            $this->getSetabilities()
        );

        return array_merge(...$entityConditions);
    }

    /**
     * @param non-empty-string $propertyName
     *
     * @return array<non-empty-string, PropertyReadableTypeInterface<object>&NamedTypeInterface&EntityBasedInterface<object>>
     */
    public function getToOneRelationshipTypes(string $propertyName): array
    {
        $behaviors = $this->toOneRelationships[$propertyName]
            ?? throw new InvalidArgumentException("To-one relationship `$propertyName` not available.");

        return $this->extractRelationshipTypes($behaviors);
    }

    /**
     * @param non-empty-string $propertyName
     *
     * @return array<non-empty-string, PropertyReadableTypeInterface<object>&NamedTypeInterface&EntityBasedInterface<object>>
     */
    public function getToManyRelationshipTypes(string $propertyName): array
    {
        $behaviors = $this->toManyRelationships[$propertyName]
            ?? throw new InvalidArgumentException("To-many relationship `$propertyName` not available");

        return $this->extractRelationshipTypes($behaviors);
    }

    /**
     * @template TRelationship of object
     *
     * @param list<RelationshipSetBehaviorInterface<TEntity, TRelationship>> $behaviors
     *
     * @return array<non-empty-string, PropertyReadableTypeInterface<TRelationship>&NamedTypeInterface&EntityBasedInterface<TRelationship>>
     */
    protected function extractRelationshipTypes(array $behaviors): array
    {
        $relationshipTypes = [];

        foreach ($behaviors as $behavior) {
            $relationshipType = $behavior->getRelationshipType();
            $typeName = $relationshipType->getTypeName();
            if (array_key_exists($typeName, $relationshipTypes)) {
                if ($relationshipTypes[$typeName] !== $relationshipType) {
                    throw new InvalidArgumentException("There are at least two update behaviors configured for the same property that have set different relationship type implementations with the same name `$typeName`. This is currently not supported.");
                }
            } else {
                $relationshipTypes[$typeName] = $relationshipType;
            }
        }

        return $relationshipTypes;
    }

    protected function getParameterConstrains(): array
    {
        return array_merge(
            ...array_values($this->attributes),
            ...array_values($this->toOneRelationships),
            ...array_values($this->toManyRelationships)
        );
    }
}
