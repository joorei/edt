<?php

declare(strict_types=1);

namespace EDT\JsonApi\Requests;

use EDT\JsonApi\Event\AfterCreationEvent;
use EDT\JsonApi\Event\BeforeCreationEvent;
use EDT\JsonApi\RequestHandling\Body\CreationRequestBody;
use EDT\JsonApi\RequestHandling\ExpectedPropertyCollectionInterface;
use EDT\JsonApi\RequestHandling\RequestTransformer;
use EDT\JsonApi\ResourceTypes\CreatableTypeInterface;
use EDT\Querying\Contracts\PathsBasedInterface;
use EDT\Wrapping\Contracts\ContentField;
use Exception;
use League\Fractal\Resource\Item;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @template TCondition of PathsBasedInterface
 * @template TSorting of PathsBasedInterface
 */
class CreationRequest
{
    public function __construct(
        protected readonly RequestTransformer $requestTransformer,
        protected readonly EventDispatcherInterface $eventDispatcher
    ) {}

    /**
     * Note that if this method returns an {@link Item} (instead of `null`), it will contain only the attributes
     * and relationships that were explicitly requested or decided as default by the given resource type implementation.
     *
     * This may conflict with the assumption, that the {@link Item} always contains the attributes and relationships,
     * that were created differently than requested by the client.
     *
     * TODO: test if the statement above is compatible with the specification and actually true regarding the libraries behavior
     *
     * @param CreatableTypeInterface<TCondition, TSorting, object> $type
     *
     * @throws Exception
     */
    public function createResource(CreatableTypeInterface $type): ?Item
    {
        $typeName = $type->getTypeName();
        $expectedProperties = $type->getExpectedInitializationProperties();

        $requestBody = $this->getCreationRequestBody($typeName, $expectedProperties);

        $beforeCreationEvent = new BeforeCreationEvent($type, $requestBody);
        $this->eventDispatcher->dispatch($beforeCreationEvent);

        $modifiedEntity = $type->createEntity($requestBody);
        $entity = $modifiedEntity->getEntity();

        $afterCreationEvent = new AfterCreationEvent($type, $entity, $requestBody);
        $this->eventDispatcher->dispatch($afterCreationEvent);

        $requestDeviations = array_merge(
            $modifiedEntity->getRequestDeviations(),
            $beforeCreationEvent->getRequestDeviations(),
            $afterCreationEvent->getRequestDeviations()
        );

        if ([] === $requestDeviations) {
            // if there were no request deviations, no response body is needed
            return null;
        }

        return new Item($entity, $type->getTransformer(), $type->getTypeName());
    }

    /**
     * @param non-empty-string $urlTypeIdentifier
     *
     * @return CreationRequestBody
     * @throws RequestException
     */
    protected function getCreationRequestBody(
        string $urlTypeIdentifier,
        ExpectedPropertyCollectionInterface $expectedProperties
    ): CreationRequestBody {
        $body = $this->requestTransformer->getRequestData(
            $urlTypeIdentifier,
            null,
            $expectedProperties
        );
        $relationships = $body[ContentField::RELATIONSHIPS] ?? [];
        [$toOneRelationships, $toManyRelationships] = $this->requestTransformer->splitRelationships($relationships);

        return new CreationRequestBody(
            $body[ContentField::ID] ?? null,
            $body[ContentField::TYPE],
            $body[ContentField::ATTRIBUTES] ?? [],
            $toOneRelationships,
            $toManyRelationships
        );
    }
}
