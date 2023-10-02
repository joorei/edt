<?php

declare(strict_types=1);

namespace Tests\ClassGeneration;

use EDT\DqlQuerying\ClassGeneration\ResourceConfigBuilderFromEntityGenerator;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\JsonApi\ResourceConfig\Builder\MagicResourceConfigBuilder;
use EDT\Parsing\Utilities\ClassOrInterfaceType;
use EDT\Parsing\Utilities\NonClassOrInterfaceType;
use EDT\PathBuilding\DocblockPropertyByTraitEvaluator;
use EDT\PathBuilding\PropertyTag;
use EDT\PathBuilding\TraitEvaluator;
use EDT\Querying\Contracts\FunctionInterface;
use PHPUnit\Framework\TestCase;

class ResourceConfigBuilderFromEntityGeneratorTest extends TestCase
{
    private const ENTITY_A_CONFIG = '<?php

declare(strict_types=1);

namespace Foobar;

use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilderInterface;
use EDT\JsonApi\PropertyConfig\Builder\ToManyRelationshipConfigBuilderInterface;
use EDT\JsonApi\PropertyConfig\Builder\ToOneRelationshipConfigBuilderInterface;
use EDT\JsonApi\ResourceConfig\Builder\MagicResourceConfigBuilder;
use EDT\Querying\Contracts\FunctionInterface;
use Tests\ClassGeneration\EntityA;
use Tests\ClassGeneration\EntityB;

/**
 * WARNING: THIS CLASS IS AUTOGENERATED.
 * MANUAL CHANGES WILL BE LOST ON RE-GENERATION.
 *
 * To add additional properties, you may want to
 * create an extending class and add them there.
 *
 * @template-extends MagicResourceConfigBuilder<FunctionInterface<bool>,OrderBySortMethodInterface,EntityA>
 *
 * @property-read AttributeConfigBuilderInterface<FunctionInterface<bool>,EntityA> $propertyA {@link EntityA::propertyA}
 * @property-read ToManyRelationshipConfigBuilderInterface<FunctionInterface<bool>,OrderBySortMethodInterface,EntityA,EntityB> $propertyB {@link EntityA::propertyB}
 * @property-read ToManyRelationshipConfigBuilderInterface<FunctionInterface<bool>,OrderBySortMethodInterface,EntityA,EntityB> $propertyC {@link EntityA::propertyC}
 * @property-read ToOneRelationshipConfigBuilderInterface<FunctionInterface<bool>,OrderBySortMethodInterface,EntityA,EntityB> $propertyD {@link EntityA::propertyD}
 * @property-read ToOneRelationshipConfigBuilderInterface<FunctionInterface<bool>,OrderBySortMethodInterface,EntityA,EntityB> $propertyE {@link EntityA::propertyE}
 * @property-read AttributeConfigBuilderInterface<FunctionInterface<bool>,EntityA> $propertyF {@link EntityA::propertyF}
 * @property-read ToManyRelationshipConfigBuilderInterface<FunctionInterface<bool>,OrderBySortMethodInterface,EntityA,EntityB> $propertyG {@link EntityA::propertyG}
 * @property-read ToManyRelationshipConfigBuilderInterface<FunctionInterface<bool>,OrderBySortMethodInterface,EntityA,EntityB> $propertyH {@link EntityA::propertyH}
 * @property-read ToOneRelationshipConfigBuilderInterface<FunctionInterface<bool>,OrderBySortMethodInterface,EntityA,EntityB> $propertyI {@link EntityA::propertyI}
 * @property-read ToOneRelationshipConfigBuilderInterface<FunctionInterface<bool>,OrderBySortMethodInterface,EntityA,EntityB> $propertyJ {@link EntityA::propertyJ}
 */
class EntityAConfig extends MagicResourceConfigBuilder
{
}
';

    public function testGenerateConfigBuilderClass(): void
    {
        $conditionClass = ClassOrInterfaceType::fromFqcn(
            FunctionInterface::class,
            [NonClassOrInterfaceType::fromRawString('bool')]
        );
        $sortingClass = ClassOrInterfaceType::fromFqcn(OrderBySortMethodInterface::class);
        $entityClass = ClassOrInterfaceType::fromFqcn(EntityA::class);
        $parentClass = ClassOrInterfaceType::fromFqcn(
            MagicResourceConfigBuilder::class,
            [$conditionClass, $sortingClass, $entityClass]
        );

        $traitEvaluator = new DocblockPropertyByTraitEvaluator(
            new TraitEvaluator(),
            [],
            [PropertyTag::PROPERTY_READ]
        );

        $generator = new ResourceConfigBuilderFromEntityGenerator(
            $conditionClass,
            $sortingClass,
            $parentClass,
            $traitEvaluator
        );

        $file = $generator->generateConfigBuilderClass(
            $entityClass,
            'EntityAConfig',
            'Foobar',
        );

        self::assertSame(self::ENTITY_A_CONFIG, (string)$file);
    }
}