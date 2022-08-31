<?php

declare(strict_types=1);

namespace EDT\DqlQuerying\Functions;

use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Expr\Comparison;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;

/**
 * @template-implements ClauseFunctionInterface<bool>
 */
class StringEndsWith extends \EDT\Querying\Functions\StringEndsWith implements ClauseFunctionInterface
{
    use ClauseBasedTrait;

    /**
     * @param ClauseFunctionInterface<string|null> $contains
     * @param ClauseFunctionInterface<string|null> $contained
     */
    public function __construct(ClauseFunctionInterface $contains, ClauseFunctionInterface $contained)
    {
        parent::__construct($contains, $contained, false);
        $this->setClauses($contains, $contained);
    }

    public function asDql(array $valueReferences, array $propertyAliases): Comparison
    {
        $expr = new Expr();
        [$contains, $contained] = $this->getDqls($valueReferences, $propertyAliases);
        return $expr->like($contains, $expr->concat('%', $contained));
    }
}
