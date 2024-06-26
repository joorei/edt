<?php

declare(strict_types=1);

namespace EDT\JsonApi\RequestHandling;

use EDT\Querying\Utilities\CollectionConstraintFactory;
use EDT\Wrapping\Contracts\ContentField;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;

trait RequestConstraintTrait
{
    private ?CollectionConstraintFactory $collectionConstraintFactory = null;

    private function getCollectionConstraintFactory(): CollectionConstraintFactory
    {
        if (null === $this->collectionConstraintFactory) {
            $this->collectionConstraintFactory = new CollectionConstraintFactory();
        }

        return $this->collectionConstraintFactory;
    }

    /**
     * Creates a constraint to validate an `id` field value.
     *
     * If an ID was given via URL (always present in update requests, optional in creation requests)
     * and you want to ensure it matches the `id` field in the request body,
     * you can pass it as `$idValue`.
     *
     * @param non-empty-string|null $idValue the `id` value if a specific one is required
     *
     * @return list<Constraint>
     */
    protected function getIdBaseConstraints(?string $idValue): array
    {
        $idConstraints = [
            new Assert\NotBlank(null, null, false, 'trim'),
            new Assert\Type('string'),
        ];

        if (null !== $idValue) {
            $idConstraints[] = new Assert\IdenticalTo($idValue);
        }

        return $idConstraints;
    }

    /**
     * @param non-empty-string $typeIdentifierValue the `type` value if a specific one is required
     *
     * @return list<Constraint>
     */
    protected function getTypeIdentifierConstraints(string $typeIdentifierValue): array
    {
        return [
            new Assert\NotBlank(null, null, false, 'trim'),
            new Assert\Type('string'),
            new Assert\IdenticalTo($typeIdentifierValue),
        ];
    }

    /**
     * @param non-empty-string $typeIdentifier
     *
     * @return list<Constraint>
     */
    protected function getConstraintsForRelationship(string $typeIdentifier): array
    {
        return [
            new Assert\NotNull(),
            new Assert\Type('array'),
            $this->getCollectionConstraintFactory()->exactMatch('relationship references', [
                ContentField::TYPE => $this->getTypeIdentifierConstraints($typeIdentifier),
                ContentField::ID => $this->getIdBaseConstraints(null),
            ]),
        ];
    }
}
