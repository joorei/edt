<?php

declare(strict_types=1);

namespace EDT\Wrapping\PropertyBehavior\Attribute\Factory;

use EDT\ConditionFactory\DrupalFilterInterface;
use EDT\JsonApi\ApiDocumentation\OptionalField;
use EDT\Querying\Contracts\PropertyAccessorInterface;
use EDT\Wrapping\PropertyBehavior\Attribute\PathAttributeSetBehavior;
use EDT\Wrapping\PropertyBehavior\PropertyUpdatabilityFactoryInterface;
use EDT\Wrapping\PropertyBehavior\PropertyUpdatabilityInterface;

/**
 * @template TEntity of object
 *
 * @template-implements PropertyUpdatabilityFactoryInterface<TEntity>
 */
class PathAttributeSetBehaviorFactory implements PropertyUpdatabilityFactoryInterface
{
    /**
     * @param list<DrupalFilterInterface> $entityConditions
     */
    public function __construct(
        protected readonly PropertyAccessorInterface $propertyAccessor,
        protected readonly array $entityConditions,
        protected readonly OptionalField $optional
    ) {}

    public function __invoke(string $name, array $propertyPath, string $entityClass): PropertyUpdatabilityInterface
    {
        return new PathAttributeSetBehavior(
            $name,
            $entityClass,
            $this->entityConditions,
            $propertyPath,
            $this->propertyAccessor,
            $this->optional
        );
    }

    public function createUpdatability(string $name, array $propertyPath, string $entityClass): PropertyUpdatabilityInterface
    {
        return $this($name, $propertyPath, $entityClass);
    }
}
