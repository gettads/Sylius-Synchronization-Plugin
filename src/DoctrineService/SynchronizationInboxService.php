<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\DoctrineService;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Gtt\SynchronizationPlugin\Api\Input\RawInputEnvelopInterface;
use Gtt\SynchronizationPlugin\DTO\Contract\TransferableInputInterface;
use Gtt\SynchronizationPlugin\DTO\Contract\TransferableItemInterface;
use Gtt\SynchronizationPlugin\Entity\SynchronizationInterface;
use Gtt\SynchronizationPlugin\Exception\SynchronizationCorruptedException;
use Gtt\SynchronizationPlugin\Processing\Client\SynchronizationInputClientInterface;
use Gtt\SynchronizationPlugin\Processing\SyncProcessor;
use Sylius\Component\Resource\Factory\Factory;

class SynchronizationInboxService
{
    public function __construct(private Factory $synchronizationFactory, private EntityManagerInterface $entityManager)
    {
    }

    public function insertEmergencySynchronization(
        RawInputEnvelopInterface $input,
        SynchronizationInputClientInterface $client,
        string $errorMessage,
    ): void
    {
        $syncEntity = $this->synchronizationFactory->createNew();

        assert($syncEntity instanceof SynchronizationInterface);

        $syncEntity->setFlowType(SynchronizationInterface::FLOW_TYPE_INCOMING);
        $syncEntity->setOperationCode($input->getOperationCode());
        $syncEntity->setType($client->getType());
        $syncEntity->setOperationId($input->getOperationId());
        $syncEntity->setPayload($input->getData());
        $syncEntity->setStatus(SynchronizationInterface::STATUS_ERROR_ON_SYNC_MAPPING);
        $syncEntity->setErrorMessage($errorMessage);

        $this->insertSyncEntities([$syncEntity]);
    }

    /**
     * @return array<string, SynchronizationInterface>
     */
    public function prepareIncomingSynchronizations(
        TransferableInputInterface $input,
        SynchronizationInputClientInterface $client,
    ): array
    {
        $synchronizations = [];

        foreach ($input->getData() as $transferableItem) {
            assert($transferableItem instanceof TransferableItemInterface);
            $payload = [
                'route' => SyncProcessor::RECEIVER_ROUTE,
                'data' => $client->getPreparedSerializer()->normalize($transferableItem),
            ];

            $syncEntity = $this->synchronizationFactory->createNew();
            assert($syncEntity instanceof SynchronizationInterface);
            $syncEntity->setFlowType(SynchronizationInterface::FLOW_TYPE_INCOMING);
            $syncEntity->setStatus(SynchronizationInterface::STATUS_SYNCHRONIZATION);
            $syncEntity->setOperationCode($client->getOperationCode());
            $syncEntity->setType($client->getType());
            $syncEntity->setOperationId($input->getOperationId());
            $syncEntity->setPayload($payload);
            $syncEntity->setSyncId($transferableItem->getSyncId());

            $synchronizations[$transferableItem->getSyncId()] = $syncEntity;
        }

        $this->insertSyncEntities($synchronizations);

        return $synchronizations;
    }

    /**
     * @param array<SynchronizationInterface> $syncEntities
     */
    private function insertSyncEntities(array $syncEntities): void
    {
        $query = sprintf(
            'INSERT INTO `%s`
               SET  `type`=:type, `flow_type`=:flowType, `operation_code`=:operationCode, `sync_id`=:syncId,
                    `operation_id`=:operationId, `payload`=:payload, `status`=:status, `created_at`=:createdAt,
                    `error_message`=:errorMessage',
            $this->entityManager->getClassMetadata(SynchronizationInterface::class)->getTableName(),
        );

        $preparedStatement = $this->entityManager->getConnection()->prepare($query);

        foreach ($syncEntities as $syncEntity) {
            $preparedStatement->executeQuery(
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
                ],
            );

            $id = $this->entityManager->getConnection()->lastInsertId();

            if ($id === false) {
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

            $syncEntity->setId((int) $id);
        }
    }
}
