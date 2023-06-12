<?php

declare(strict_types=1);

namespace EDT\Wrapping\WrapperFactories;

use EDT\JsonApi\Schema\ContentField;
use EDT\Querying\Contracts\PathException;
use EDT\Querying\Contracts\PathsBasedInterface;
use EDT\Querying\Contracts\PropertyAccessorInterface;
use EDT\Querying\Contracts\SortException;
use EDT\Wrapping\Contracts\AccessException;
use EDT\Wrapping\Contracts\Types\ExposableRelationshipTypeInterface;
use EDT\Wrapping\Contracts\Types\TransferableTypeInterface;
use EDT\Wrapping\Contracts\Types\TypeInterface;
use EDT\Wrapping\Properties\AttributeReadabilityInterface;
use EDT\Wrapping\Properties\IdAttributeConflictException;
use EDT\Wrapping\Properties\IdReadabilityInterface;
use InvalidArgumentException;
use function array_key_exists;

/**
 * Creates a wrapper around an instance of a {@link TypeInterface::getEntityClass() backing object}.
 */
class WrapperArrayFactory
{
    /**
     * @param int<0, max> $depth
     *
     * @throws InvalidArgumentException Thrown if the given depth is negative.
     */
    public function __construct(
        private readonly PropertyAccessorInterface $propertyAccessor,
        private readonly int $depth
    ) {}

    /**
     * Converts the given object into an array with the object's property names as array keys and the
     * property values as array values. Only properties that are defined as readable by
     * {@link TransferableTypeInterface::getReadableProperties()} are included. Relationships to
     * other types will be copied recursively in the same manner, but only if they're
     * allowed to be accessed. If they are allowed to be accessed depends on their
     * {@link ExposableRelationshipTypeInterface::isExposedAsRelationship()} and
     * {@link TypeInterface::getAccessCondition()} methods, both must return `true` for the property to be included.
     *
     * The recursion stops when the specified depth in {@link WrapperArrayFactory::$depth} is reached.
     *
     * If for example the specified depth is 0 and the given type is a Book with a
     * `title` string property and an author relationship to another type then
     * (assuming all properties are accessible as defined above) an array with the keys `title` and `author`
     * will be returned with `title` being a string and `author` being `null`.
     *
     * Assuming the `title` property was not readable then it would not be present in the
     * returned array at all.
     *
     * If depth is set to `1` then the value for `author` would be an array with all
     * accessible properties of the `author` type as keys. However, the recursion
     * would stop at the author and the values to relationships from the `author` property
     * to other types would be set to `null`.
     *
     * Each attribute value corresponding to the readable property name will be replaced
     * with the value read using the property path corresponding to the $propertyName.
     *
     * For each relationship the same will be done, but additionally it will be recursively
     * wrapped using this factory until the depth set in this instance is reached. If access is not granted due to the
     * settings in the corresponding {@link TypeInterface::getAccessCondition()} it will be
     * replaced by `null`.
     *
     * If a relationship is referenced each value will be checked using {@link TypeInterface::getAccessCondition()}
     * if it should be included, if so it is wrapped using this factory and included in the result.
     *
     * @template TCondition of PathsBasedInterface
     * @template TSorting of PathsBasedInterface
     *
     * @param TransferableTypeInterface<TCondition, TSorting, object> $type
     *
     * @return array<non-empty-string, mixed> an array containing the readable properties of the given type
     *
     * @throws AccessException Thrown if $type is not available.
     * @throws PathException
     * @throws SortException
     */
    public function createWrapper(object $entity, TransferableTypeInterface $type): array
    {
        // we only include properties in the result array that are actually accessible
        $readableProperties = $type->getReadableProperties();

        // TODO: respect $readability settings (default field, default include)?
        // TODO: add sparse fieldset support

        $idReadability = $type->getIdentifierReadability();
        $attributes = $readableProperties[0];
        if (array_key_exists(ContentField::ID, $attributes)) {
            throw IdAttributeConflictException::create($type->getIdentifier());
        }
        $attributes[ContentField::ID] = $idReadability;

        $wrapperArray = array_map(
            static fn (AttributeReadabilityInterface|IdReadabilityInterface $readability) => $readability->getValue($entity),
            $attributes
        );

        $relationshipWrapperFactory = $this->getNextWrapperFactory();

        foreach ($readableProperties[1] as $propertyName => $readability) {
            $targetEntity = $readability->getValue($entity, []);
            if (null === $targetEntity) {
                $wrapperArray[$propertyName] = null;
            } else {
                $relationshipType = $readability->getRelationshipType();
                $wrapperArray[$propertyName] = $relationshipWrapperFactory
                    ->createWrapper($targetEntity, $relationshipType);
            }
        }

        foreach ($readableProperties[2] as $propertyName => $readability) {
            $relationshipType = $readability->getRelationshipType();
            $relationshipEntities = $readability->getValue($entity, [], []);
            $wrapperArray[$propertyName] = array_map(
                static fn (object $objectToWrap) => $relationshipWrapperFactory
                    ->createWrapper($objectToWrap, $relationshipType),
                $relationshipEntities
            );
        }

        return $wrapperArray;
    }

    protected function getNextWrapperFactory(): self|ArrayEndWrapperFactory
    {
        $newDepth = $this->depth - 1;

        return 0 > $newDepth
            ? new ArrayEndWrapperFactory()
            : new self($this->propertyAccessor, $newDepth);
    }
}
