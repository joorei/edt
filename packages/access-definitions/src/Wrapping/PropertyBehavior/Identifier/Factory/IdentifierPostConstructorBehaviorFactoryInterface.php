<?php

declare(strict_types=1);

namespace EDT\Wrapping\PropertyBehavior\Identifier\Factory;

use EDT\Wrapping\PropertyBehavior\Identifier\IdentifierPostConstructorBehaviorInterface;

interface IdentifierPostConstructorBehaviorFactoryInterface
{
    /**
     * @template TEntity of object
     *
     * @param non-empty-list<non-empty-string> $propertyPath
     * @param class-string<TEntity> $entityClass
     *
     * @return IdentifierPostConstructorBehaviorInterface<TEntity>
     */
    public function __invoke(array $propertyPath, string $entityClass): IdentifierPostConstructorBehaviorInterface;

    /**
     * @template TEntity of object
     *
     * @param non-empty-list<non-empty-string> $propertyPath
     * @param class-string<TEntity> $entityClass
     *
     * @return IdentifierPostConstructorBehaviorInterface<TEntity>
     *
     * @deprecated call instance directly as callable instead (i.e. indirectly using {@link __invoke})
     */
    public function createIdentifierPostConstructorBehavior(array $propertyPath, string $entityClass): IdentifierPostConstructorBehaviorInterface;
}
