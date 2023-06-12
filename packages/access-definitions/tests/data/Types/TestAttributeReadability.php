<?php

declare(strict_types=1);

namespace Tests\data\Types;

use EDT\Querying\Contracts\PropertyAccessorInterface;
use EDT\Wrapping\Properties\AttributeReadabilityInterface;

class TestAttributeReadability implements AttributeReadabilityInterface
{
    public function __construct(
        private readonly array $propertyPath,
        private readonly PropertyAccessorInterface $propertyAccessor
    ) {}

    public function getPropertySchema(): array
    {
        return [];
    }

    public function getValue(object $entity): mixed
    {
        return $this->propertyAccessor->getValueByPropertyPath($entity, ...$this->propertyPath);
    }

    public function isDefaultField(): bool
    {
        return false;
    }
}
