<?php

declare(strict_types=1);

namespace EDT\Querying\ConditionParsers\Drupal;

use EDT\ConditionFactory\ConditionGroupFactoryInterface;
use EDT\JsonApi\RequestHandling\FilterParserInterface;
use EDT\Querying\Contracts\ConditionParserInterface;
use function count;
use function in_array;

/**
 * Provides functions to convert data from HTTP requests into condition instances.
 *
 * The data is expected to be in the format defined by the Drupal JSON:API filter specification.
 *
 * @phpstan-type DrupalValue = simple_primitive|array<int|string, mixed>|null
 * @phpstan-type DrupalFilterGroup = array{
 *            conjunction: 'AND'|'OR',
 *            memberOf?: non-empty-string
 *          }
 * @phpstan-type DrupalFilterCondition = array{
 *            path?: non-empty-string,
 *            value?: DrupalValue,
 *            operator?: non-empty-string,
 *            memberOf?: non-empty-string
 *          }
 * @template TCondition
 * @template-implements FilterParserInterface<array<non-empty-string, array{condition: DrupalFilterCondition}|array{group: DrupalFilterGroup}>, TCondition>
 */
class DrupalFilterParser implements FilterParserInterface
{
    /**
     * The maximum number of steps we make inside the tree to be built from the given condition groups.
     *
     * Exceeding this count indicates a that a group references itself as parent, directly or indirectly.
     *
     * If this count does not suffice for a (realistic) use case it can be increased further. Just
     * keep DoS attacks in mind when doing so.
     *
     * @var positive-int
     */
    protected readonly int $maxIterations;

    /**
     * The key identifying a field as data for a filter group.
     */
    public const GROUP = 'group';

    /**
     * Any condition in the group must apply.
     */
    public const OR = 'OR';

    /**
     * This group/condition key is reserved and can not be used in a request.
     *
     * The value is not specified by Drupal's JSON:API filter documentation. However,
     * it is used by Drupal's implementation and was thus adopted here and preferred over
     * alternatives like 'root' or '' (empty string).
     */
    public const ROOT = '@root';

    /**
     * The key of the field determining which filter group a condition or a subgroup is a member
     * of.
     */
    public const MEMBER_OF = 'memberOf';

    /**
     * All conditions in the group must apply.
     */
    public const AND = 'AND';

    /**
     * The key identifying a field as data for a filter condition.
     */
    public const CONDITION = 'condition';

    /**
     * The key for the field in which "AND" or "OR" is stored.
     */
    public const CONJUNCTION = 'conjunction';

    public const PATH = 'path';

    public const OPERATOR = 'operator';

    public const VALUE = 'value';

    /**
     * @param ConditionGroupFactoryInterface<TCondition> $conditionGroupFactory
     * @param ConditionParserInterface<TCondition> $conditionParser
     * @param positive-int $maxIterations How deep groups are allowed to be nested.
     */
    public function __construct(
        protected readonly ConditionGroupFactoryInterface $conditionGroupFactory,
        protected readonly ConditionParserInterface $conditionParser,
        protected readonly DrupalFilterValidator $filterValidator,
        int $maxIterations = 5000
    ) {
        $this->maxIterations = $maxIterations;
    }

    /**
     * The returned conditions are to be applied in an `AND` manner, i.e. all conditions must
     * match for an entity to match the Drupal filter. An empty error being returned means that
     * all entities match, as there are no restrictions.
     *
     * @param array<non-empty-string, array{condition: DrupalFilterCondition}|array{group: DrupalFilterGroup}> $filter
     *
     * @return list<TCondition>
     *
     * @throws DrupalFilterException
     */
    public function parseFilter($filter): array
    {
        $drupalFilter = new DrupalFilter($filter);
        $groupedConditions = $drupalFilter->getGroupedConditions();
        $conditions = $this->parseConditions($groupedConditions);

        // If no buckets with conditions exist we can return right away
        if (0 === count($conditions)) {
            return [];
        }

        $groupNameToMemberOf = $drupalFilter->getGroupNameToMemberOf();

        // We use the indices as information source and work on the $conditions
        // array only.

        // The buckets may be stored in a flat list, but logically we're working with a
        // tree of buckets. (Except if the request contained a group circle. Then
        // it is not a tree anymore, and we can't parse it.) To merge the buckets we need
        // to process that tree from the leaves up. To do so we search for buckets that
        // are not needed as parent group by any other bucket. These buckets are merged
        // into a single condition, which is then added to its parent bucket. This is
        // repeated until only the root bucket remains.
        $emergencyCounter = $this->maxIterations;
        while (0 !== count($conditions) && !$this->hasReachedRootGroup($conditions)) {
            if (0 > --$emergencyCounter) {
                throw DrupalFilterException::emergencyAbort($this->maxIterations);
            }
            foreach ($conditions as $bucketName => $bucket) {
                if (self::ROOT === $bucketName) {
                    continue;
                }

                // If no conjunction definition for this group name exists we can remove it,
                // as the specification says to ignore such groups.
                if (!$drupalFilter->hasGroup($bucketName)) {
                    unset($conditions[$bucketName]);
                    continue;
                }

                // If the current bucket is not used as parent by any other group
                // then we can merge it and move the merged result into the parent
                // group. Afterwards the entry must be removed from the index to
                // mark it as no longer needed as by a parent.
                $usedAsParentGroup = in_array($bucketName, $groupNameToMemberOf, true);
                if (!$usedAsParentGroup) {
                    $conjunction = $drupalFilter->getGroupConjunction($bucketName);
                    $parentGroupKey = $drupalFilter->getFilterGroupParent($bucketName);
                    $conditionsToMerge = $conditions[$bucketName];
                    $additionalCondition = 1 === count($conditionsToMerge)
                        ? array_pop($conditionsToMerge)
                        : $this->createGroup($conjunction, $conditionsToMerge);
                    $conditions[$parentGroupKey][] = $additionalCondition;
                    unset($conditions[$bucketName], $groupNameToMemberOf[$bucketName]);
                }
            }
        }

        return $conditions[self::ROOT] ?? [];
    }

    /**
     * @deprecated call {@link DrupalFilterValidator} manually
     */
    public function validateFilter(mixed $filter): array
    {
        $this->filterValidator->validateFilter($filter);

        return $filter;
    }

    /**
     * @param self::AND|self::OR $conjunction
     * @param non-empty-list<TCondition> $conditions
     * @return TCondition
     *
     * @throws DrupalFilterException
     */
    protected function createGroup(string $conjunction, array $conditions)
    {
        return match ($conjunction) {
            self::AND => $this->conditionGroupFactory->allConditionsApply(...$conditions),
            self::OR => $this->conditionGroupFactory->anyConditionApplies(...$conditions),
            default => throw DrupalFilterException::conjunctionUnavailable($conjunction),
        };
    }

    /**
     * @param array<non-empty-string, list<TCondition|null>> $conditions
     */
    protected function hasReachedRootGroup(array $conditions): bool
    {
        return 1 === count($conditions) && self::ROOT === array_key_first($conditions);
    }

    /**
     * @param array<non-empty-string, non-empty-list<DrupalFilterCondition>> $groupedConditions
     *
     * @return array<non-empty-string, non-empty-list<TCondition>>
     */
    protected function parseConditions(array $groupedConditions): array
    {
        return array_map(
            fn (array $conditionGroup): array => array_map(
                [$this->conditionParser, 'parseCondition'],
                $conditionGroup
            ),
            $groupedConditions
        );
    }
}
