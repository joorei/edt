<?php

declare(strict_types=1);

namespace EDT\JsonApi\ResourceConfig;

use EDT\JsonApi\PropertyConfig\AttributeConfigInterface;
use EDT\JsonApi\PropertyConfig\IdentifierConfigInterface;
use EDT\JsonApi\PropertyConfig\PropertyConfigInterface;
use EDT\JsonApi\PropertyConfig\RelationshipConfigInterface;
use EDT\JsonApi\PropertyConfig\ToManyRelationshipConfigInterface;
use EDT\JsonApi\PropertyConfig\ToOneRelationshipConfigInterface;
use EDT\Querying\PropertyPaths\PropertyLinkInterface;
use EDT\Querying\Utilities\Iterables;
use EDT\Wrapping\Contracts\ContentField;
use EDT\Wrapping\PropertyBehavior\Attribute\AttributeReadabilityInterface;
use EDT\Wrapping\PropertyBehavior\ConstructorBehaviorInterface;
use EDT\Wrapping\PropertyBehavior\PropertySetBehaviorInterface;
use EDT\Wrapping\PropertyBehavior\PropertyUpdatabilityInterface;
use EDT\Wrapping\PropertyBehavior\Relationship\ToMany\ToManyRelationshipReadabilityInterface;
use EDT\Wrapping\PropertyBehavior\Relationship\ToOne\ToOneRelationshipReadabilityInterface;
use EDT\Wrapping\ResourceBehavior\ResourceInstantiability;
use EDT\Wrapping\ResourceBehavior\ResourceReadability;
use EDT\Wrapping\ResourceBehavior\ResourceUpdatability;
use Webmozart\Assert\Assert;

/**
 * @template TEntity of object
 *
 * @template-implements ResourceConfigInterface<TEntity>
 */
class ResourceConfig implements ResourceConfigInterface
{
    /**
     * @var array<non-empty-string, AttributeConfigInterface<TEntity>|RelationshipConfigInterface<TEntity, object>>
     */
    protected readonly array $propertyConfigs;

    /**
     * @param class-string<TEntity> $entityClass
     * @param IdentifierConfigInterface<TEntity> $identifierConfig
     * @param array<non-empty-string, AttributeConfigInterface<TEntity>> $attributeConfigs
     * @param array<non-empty-string, ToOneRelationshipConfigInterface<TEntity, object>> $toOneRelationshipConfigs
     * @param array<non-empty-string, ToManyRelationshipConfigInterface<TEntity, object>> $toManyRelationshipConfigs
     * @param list<ConstructorBehaviorInterface> $generalConstructorBehaviors
     * @param list<PropertySetBehaviorInterface<TEntity>> $generalPostConstructorBehaviors
     * @param list<PropertyUpdatabilityInterface<TEntity>> $generalUpdateBehaviors
     */
    public function __construct(
        protected readonly string $entityClass,
        protected readonly IdentifierConfigInterface $identifierConfig,
        protected readonly array $attributeConfigs,
        protected readonly array $toOneRelationshipConfigs,
        protected readonly array $toManyRelationshipConfigs,
        protected readonly array $generalConstructorBehaviors,
        protected readonly array $generalPostConstructorBehaviors,
        protected readonly array $generalUpdateBehaviors
    ) {
        $this->propertyConfigs = array_merge(
            $this->attributeConfigs,
            $this->toOneRelationshipConfigs,
            $this->toManyRelationshipConfigs
        );
        Assert::keyNotExists($this->propertyConfigs, ContentField::ID);
        Assert::keyNotExists($this->propertyConfigs, ContentField::TYPE);
        Assert::count(
            $this->propertyConfigs,
            count($this->attributeConfigs) + count($this->toOneRelationshipConfigs) + count($this->toManyRelationshipConfigs)
        );
    }

    public function getInstantiability(): ResourceInstantiability
    {
        $constructorParameterConfigs = array_map(
            static fn (PropertyConfigInterface $property): array => $property->getConstructorBehaviors(),
            $this->propertyConfigs
        );

        $setabilities = array_map(
            static fn (PropertyConfigInterface $property): array => $property->getPostConstructorBehaviors(),
            $this->propertyConfigs
        );

        Assert::keyNotExists($constructorParameterConfigs, ContentField::ID);
        $identifierConstructorBehavior = $this->identifierConfig->getConstructorBehaviors();
        $constructorParameterConfigs[ContentField::ID] = $identifierConstructorBehavior;

        return new ResourceInstantiability(
            $this->entityClass,
            $constructorParameterConfigs,
            $this->generalConstructorBehaviors,
            $setabilities,
            $this->generalPostConstructorBehaviors,
            $this->identifierConfig->getPostConstructorBehaviors()
        );
    }

    public function getUpdatability(): ResourceUpdatability
    {
        $attributes = array_map(
            static fn(AttributeConfigInterface $property): array => $property->getUpdateBehaviors(),
            $this->attributeConfigs
        );
        $toOneRelationships = array_map(
            static fn(ToOneRelationshipConfigInterface $property): array => $property->getUpdateBehaviors(),
            $this->toOneRelationshipConfigs
        );
        $toManyRelationships = array_map(
            static fn(ToManyRelationshipConfigInterface $property): array => $property->getUpdateBehaviors(),
            $this->toManyRelationshipConfigs
        );

        return new ResourceUpdatability($attributes, $toOneRelationships, $toManyRelationships, $this->generalUpdateBehaviors);
    }

    public function getReadability(): ResourceReadability
    {
        return new ResourceReadability(
            Iterables::removeNull(array_map(
                static fn (AttributeConfigInterface $property): ?AttributeReadabilityInterface => $property->getReadability(),
                $this->attributeConfigs
            )),
            Iterables::removeNull(array_map(
                static fn (ToOneRelationshipConfigInterface $property): ?ToOneRelationshipReadabilityInterface => $property->getReadability(),
                $this->toOneRelationshipConfigs
            )),
            Iterables::removeNull(array_map(
                static fn (ToManyRelationshipConfigInterface $property): ?ToManyRelationshipReadabilityInterface => $property->getReadability(),
                $this->toManyRelationshipConfigs
            )),
            $this->identifierConfig->getReadability()
        );
    }

    public function getSortingProperties(): array
    {
        $properties = Iterables::removeNull(array_map(
            static fn (AttributeConfigInterface|RelationshipConfigInterface $property): ?PropertyLinkInterface => $property->getSortLink(),
            $this->propertyConfigs
        ));

        $identifierSortLink = $this->identifierConfig->getSortLink();
        if (null !== $identifierSortLink) {
            $properties[ContentField::ID] = $identifierSortLink;
        }

        return $properties;
    }

    public function getFilteringProperties(): array
    {
        $properties = Iterables::removeNull(array_map(
            static fn (AttributeConfigInterface|RelationshipConfigInterface $property): ?PropertyLinkInterface => $property->getFilterLink(),
            $this->propertyConfigs
        ));

        $identifierFilterLink = $this->identifierConfig->getFilterLink();
        if (null !== $identifierFilterLink) {
            $properties[ContentField::ID] = $identifierFilterLink;
        }

        return $properties;
    }
}
