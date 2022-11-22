<?php

declare(strict_types=1);

namespace EDT\JsonApi\Properties;

use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use EDT\Querying\Contracts\PathException;
use EDT\Querying\Contracts\PropertyPathInterface;

/**
 * @template TCondition of \EDT\Querying\Contracts\PathsBasedInterface
 * @template TSorting of \EDT\Querying\Contracts\PathsBasedInterface
 * @template TEntity of object
 */
class TypedPathConfigCollection
{
    /**
     * @var ConfigCollection<TCondition, TSorting, TEntity>
     */
    protected ConfigCollection $configCollection;

    /**
     * @param ConfigCollection<TCondition, TSorting, TEntity> $configCollection
     */
    public function __construct(ConfigCollection $configCollection)
    {
        $this->configCollection = $configCollection;
    }

    /**
     * @param PropertyPathInterface $propertyPath
     *
     * @return AttributeConfig<TCondition, TEntity>
     *
     * @throws ResourcePropertyConfigException
     * @throws PathException
     */
    public function configureAttribute($propertyPath, bool $replace = false): AttributeConfig
    {
        $this->validatePathStart($propertyPath);
        return $this->configCollection->configureAttribute($propertyPath, $replace);
    }

    /**
     * @template TRelationship of object
     * @template TRelationshipType of \EDT\JsonApi\ResourceTypes\ResourceTypeInterface<TCondition, TSorting, TRelationship>
     *
     * @param PropertyPathInterface&TRelationshipType $relationship
     *
     * @return ToOneRelationshipConfig<TCondition, TSorting, TEntity, TRelationship, PropertyPathInterface&TRelationshipType>
     *
     * @throws ResourcePropertyConfigException
     * @throws PathException
     */
    public function configureToOneRelationship(ResourceTypeInterface $relationship, bool $replace = false): ToOneRelationshipConfig
    {
        $this->validatePathStart($relationship);
        return $this->configCollection->configureToOneRelationship($relationship, $relationship, $replace);
    }

    /**
     * @template TRelationship of object
     * @template TRelationshipType of \EDT\JsonApi\ResourceTypes\ResourceTypeInterface<TCondition, TSorting, TRelationship>
     *
     * @param PropertyPathInterface&TRelationshipType $relationship
     *
     * @return ToManyRelationshipConfig<TCondition, TSorting, TEntity, TRelationship, PropertyPathInterface&TRelationshipType>
     *
     * @throws ResourcePropertyConfigException
     * @throws PathException
     */
    public function configureToManyRelationship(ResourceTypeInterface $relationship, bool $replace = false): ToManyRelationshipConfig
    {
        $this->validatePathStart($relationship);
        return $this->configCollection->configureToManyRelationship($relationship, $relationship, $replace);
    }

    /**
     * @param PropertyPathInterface $path
     *
     * @throws ResourcePropertyConfigException
     */
    protected function validatePathStart(PropertyPathInterface $path): void
    {
        if (!is_subclass_of($path, '\EDT\PathBuilding\PropertyAutoPathInterface')) {
            return;
        }

        $expectedType = $this->configCollection->getType();
        $actualType = $path->getAsValues()[0];

        if ($expectedType === $actualType) {
            return;
        }

        throw ResourcePropertyConfigException::invalidStart($expectedType->getIdentifier());
    }
}