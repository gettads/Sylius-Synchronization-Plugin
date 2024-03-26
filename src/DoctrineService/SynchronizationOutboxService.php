<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\DoctrineService;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Gtt\SynchronizationPlugin\Contract\SyncableEntityInterface;
use Gtt\SynchronizationPlugin\DoctrineEventListener\DTO\EntityChangeCollectionDto;
use Gtt\SynchronizationPlugin\DoctrineEventListener\DTO\EntityChangeDto;
use Gtt\SynchronizationPlugin\DoctrineService\SynchronizationAttributeDataExtractor;
use Gtt\SynchronizationPlugin\DTO\Contract\TransferableOutputInterface;
use Gtt\SynchronizationPlugin\Entity\SynchronizationInterface;
use Gtt\SynchronizationPlugin\Exception\SynchronizationConfigException;
use Gtt\SynchronizationPlugin\Exception\SynchronizationCorruptedException;
use Gtt\SynchronizationPlugin\Processing\Client\SynchronizationClientInterface;
use Gtt\SynchronizationPlugin\Processing\Client\SynchronizationOutputClientInterface;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Resource\Factory\Factory;
use Sylius\Component\Resource\Model\ResourceInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class SynchronizationOutboxService
{
    public function __construct(
        private Factory $synchronizationFactory,
        private SynchronizationAttributeDataExtractor $extractor,
        private Security $security,
        private RequestStack $requestStack,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array<SynchronizationInterface>
     *
     * @throws SynchronizationConfigException
     */
    public function prepareOutcomingSynchronizations(
        EntityChangeCollectionDto $dto,
        SynchronizationOutputClientInterface $client,
    ): array {
        $syncableEntities = $data = [];

        foreach ($dto->getChanges() as $item) {
            assert($item instanceof EntityChangeDto);
            $resource = $item->getResource();
            $crudType = $item->getType();

            if ($this->isStop($client, $resource, $crudType)) {
                continue;
            }

            $syncableEntity = $resource instanceof SyncableEntityInterface
                ? $resource
                : $this->extractor->getSyncableEntity($resource, $client);

            if ($syncableEntity === null) {
                throw new SynchronizationConfigException(
                    sprintf(
                        'Client: %s, operation: %s, resource: %s. Syncable entities not found.',
                        $client::class,
                        $client->getOperationCode(),
                        $resource::class
                    ),
                );
            }

            $syncableEntities[$syncableEntity::class . ':' . $syncableEntity->getId()] = $syncableEntity;
            $changes = $this->getPayloadChanges($item, $crudType);

            if ((bool)$changes) {
                $syncId = $syncableEntity->getSyncId() ?? Uuid::uuid4()->toString();
                $syncableEntity->setSyncId($syncId);
                $data[$syncId][$crudType][$resource::class][$resource->getId()] = $changes;
            }
        }

        return $this->createOutcomingSynchronizations($client, $syncableEntities, $data);
    }

    public function updateOutcomingSynchronizationByTransferable(
        SynchronizationOutputClientInterface $client,
        SynchronizationInterface $syncEntity,
        TransferableOutputInterface $transferable,
    ): void {
        if ($syncEntity->getId() === null) {
            throw new SynchronizationCorruptedException(
                'Can not update synchronization\'s payload: ID is not set.',
            );
        }

        $payload = $syncEntity->getPayload();
        $payload['transfer_object'] = $client->getPreparedSerializer()->normalize($transferable);

        $syncEntity->setPayload($payload);

        $query = sprintf(
            'UPDATE `%s` SET `payload` = :payload, `status` = :status WHERE `id` = :id',
            $this->entityManager->getClassMetadata(SynchronizationInterface::class)->getTableName(),
        );

        $this->entityManager->getConnection()->prepare($query)->executeQuery(
            [
                'payload' => json_encode($payload),
                'status' => SynchronizationInterface::STATUS_SYNCHRONIZATION,
                'id' => $syncEntity->getId(),
            ]
        );
    }

    public function deleteSynchronization(SynchronizationInterface $synchronization): void
    {
        $query = sprintf(
            'DELETE FROM `%s` WHERE `id` = :id',
            $this->entityManager->getClassMetadata(SynchronizationInterface::class)->getTableName(),
        );

        $this->entityManager->getConnection()->prepare($query)->executeQuery(['id' => $synchronization->getId()]);
    }

    public function updateStatus(SynchronizationInterface $synchronization, string $status, string $message = ''): void
    {
        if (!in_array($status, SynchronizationInterface::STATUSES, true)) {
            $status = 'unknown_status:' . $status;
        }

        $query = sprintf(
            'UPDATE `%s` SET `status` = :status, `error_message` = :message, `updated_at` = :updatedAt
                WHERE `id` = :id',
            $this->entityManager->getClassMetadata(SynchronizationInterface::class)->getTableName(),
        );

        $this->entityManager->getConnection()->prepare($query)->executeQuery(
            [
                'status' => $status,
                'message' => $message,
                'updatedAt' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                'id' => $synchronization->getId(),
            ]
        );
    }

    /**
     * @param array<SyncableEntityInterface> $syncableEntities
     * @param array<string, array<string, array<string, string|int|bool|float|array|null>>> $data
     *
     * @return array<SynchronizationInterface>
     */
    private function createOutcomingSynchronizations(
        SynchronizationClientInterface $client,
        array $syncableEntities,
        array $data
    ): array {
        $collection = [];
        $operationId = Uuid::uuid4()->toString();

        foreach ($syncableEntities as $syncableEntity) {
            if (!isset($data[$syncableEntity->getSyncId()])) {
                continue;
            }

            $payload = [
                'user' => $this->security->getUser()?->getUserIdentifier(),
                'route' => $this->requestStack->getCurrentRequest()?->get('_route'),
                'data' => $data[$syncableEntity->getSyncId()],
            ];

            $syncEntity = $this->synchronizationFactory->createNew();
            assert($syncEntity instanceof SynchronizationInterface);
            $syncEntity->setFlowType(SynchronizationInterface::FLOW_TYPE_OUTCOMING);
            $syncEntity->setOperationCode($client->getOperationCode());
            $syncEntity->setType($client->getType());
            $syncEntity->setOperationId($operationId);
            $syncEntity->setPayload($payload);
            $syncEntity->setSyncId($syncableEntity->getSyncId());

            $this->insertSyncEntity($syncEntity);
            $this->updateSyncableEntity($syncableEntity);

            $collection[] = $syncEntity;
        }

        return $collection;
    }

    private function updateSyncableEntity(SyncableEntityInterface $entity): void
    {
        if ($entity->getSyncId() === null) {
            return;
        }

        $query = sprintf(
            'UPDATE `%s` SET `sync_id` = :syncId WHERE id = :id',
            $this->entityManager->getClassMetadata($entity::class)->getTableName(),
        );

        $this->entityManager->getConnection()->prepare($query)->executeQuery(
            [
                'syncId' => $entity->getSyncId(),
                'id' => $entity->getId(),
            ]
        );
    }

    private function insertSyncEntity(SynchronizationInterface $syncEntity): void
    {
        $query = sprintf(
            'INSERT INTO `%s`
              SET `type`=:type, `flow_type`=:flowType, `operation_code`=:operationCode, `sync_id`=:syncId,
                  `operation_id`=:operationId, `payload`=:payload, `status`=:status, `created_at`=:createdAt,
                  `error_message`=:errorMessage',
            $this->entityManager->getClassMetadata(SynchronizationInterface::class)->getTableName(),
        );

        $this->entityManager->getConnection()->prepare($query)->executeQuery(
            [
                'type' => $syncEntity->getType(),
                'flowType' => $syncEntity->getFlowType(),
                'operationCode' => $syncEntity->getOperationCode(),
                'syncId' => $syncEntity->getSyncId(),
                'operationId' => $syncEntity->getOperationId(),
                'payload' => json_encode($syncEntity->getPayload()),
                'status' => $syncEntity->getStatus(),
                'createdAt' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                'errorMessage' => $syncEntity->getErrorMessage(),
            ]
        );

        $id = $this->entityManager->getConnection()->lastInsertId();

        if ((bool)$id === false) {
            throw new SynchronizationCorruptedException(
                sprintf(
                    'Can not insert new %s synchronization entity.
                        OperationCode: %s, syncId: %s, operationId: %s, status: %s, errorMessage: %s',
                    $syncEntity->getFlowType(),
                    $syncEntity->getOperationCode(),
                    $syncEntity->getSyncId(),
                    $syncEntity->getOperationId(),
                    $syncEntity->getStatus(),
                    $syncEntity->getErrorMessage(),
                ),
            );
        }

        $syncEntity->setId((int)$id);
    }

    private function isStop(
        SynchronizationClientInterface $client,
        ResourceInterface $resource,
        string $crudType
    ): bool {
        return
            $resource instanceof SynchronizationInterface
            || !in_array($client->getOperationCode(), $this->extractor->extractOperationCodes($resource), true)
            || !in_array($crudType, $client->getCrudTypes(), true);
    }

    /**
     * @return array<string, array<string, array<string, string|int|bool|float|array|null>>>
     */
    private function getPayloadChanges(EntityChangeDto $dto, string $crudType): array
    {
        $resource = $dto->getResource();
        $changesData = $crudType === SynchronizationClientInterface::CRUD_TYPE_UPDATE
            ? []
            : [
                'id' => [
                    'old' => $crudType === SynchronizationClientInterface::CRUD_TYPE_CREATE ? null : $resource->getId(),
                    'new' => $crudType === SynchronizationClientInterface::CRUD_TYPE_DELETE ? null : $resource->getId(),
                ],
            ];

        foreach ($dto->getChanges() as $attribute => $oldNewData) {
            assert(is_array($oldNewData));
            [$old, $new] = array_pad($oldNewData, 2, null);
            $old = $old instanceof ResourceInterface ? $old->getId() : $old;
            $new = $new instanceof ResourceInterface ? $new->getId() : $new;
            $changesData[$attribute] = ['old' => $old, 'new' => $new];
        }

        return $changesData;
    }
}
