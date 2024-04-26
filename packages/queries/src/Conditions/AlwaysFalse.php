<?php

declare(strict_types=1);

namespace EDT\Querying\Conditions;

use EDT\Querying\Contracts\PathsBasedInterface;
use Webmozart\Assert\Assert;

/**
 * @template TCondition of PathsBasedInterface
 *
 * @template-extends AbstractCondition<TCondition>
 * @template-implements ValueIndependentConditionInterface<TCondition>
 */
class AlwaysFalse extends AbstractCondition implements ValueIndependentConditionInterface
{
    public const OPERATOR = 'FALSE';

    public function getOperator(): string
    {
        return self::OPERATOR;
    }

    public function transform(?array $path): PathsBasedInterface
    {
        Assert::null($path);

        return $this->conditionFactory->false();
    }
}
