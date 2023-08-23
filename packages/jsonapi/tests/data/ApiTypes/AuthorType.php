<?php

declare(strict_types=1);

namespace Tests\data\ApiTypes;

use EDT\JsonApi\Properties\Attributes\PathAttributeReadability;
use EDT\JsonApi\Properties\Id\PathIdReadability;
use EDT\JsonApi\Properties\Relationships\PathToManyRelationshipReadability;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use EDT\Wrapping\Properties\EntityReadability;
use League\Fractal\TransformerAbstract;

class AuthorType extends \Tests\data\Types\AuthorType implements ResourceTypeInterface
{
    public function getTypeName(): string
    {
        return self::class;
    }

    /**
     * Overwrites its parent relationships with reference to resource type implementations.
     */
    public function getReadableProperties(): EntityReadability
    {
        return new EntityReadability(
            [
                'name' => new PathAttributeReadability(
                    $this->getEntityClass(),
                    ['name'],
                    false,
                    $this->propertyAccessor,
                    $this->typeResolver
                ),
                'pseudonym' => new PathAttributeReadability(
                    $this->getEntityClass(),
                    ['pseudonym'],
                    false,
                    $this->propertyAccessor,
                    $this->typeResolver,
                ),
                'birthCountry' => new PathAttributeReadability(
                    $this->getEntityClass(),
                    ['birth', 'country'],
                    false,
                    $this->propertyAccessor,
                    $this->typeResolver
                ),
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
            new PathIdReadability(
                $this->getEntityClass(),
                ['id'],
                $this->propertyAccessor,
                $this->typeResolver
            )
        );
    }

    public function getTransformer(): TransformerAbstract
    {
    }
}
