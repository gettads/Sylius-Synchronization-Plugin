<?php

declare(strict_types=1);

namespace Tests\Gtt\SynchronizationPlugin\Application\Synchronization\Processing;

use Gtt\SynchronizationPlugin\Api\Input\ReceiveOperation;
use Gtt\SynchronizationPlugin\DTO\Contract\TransferableInputInterface;
use Gtt\SynchronizationPlugin\DTO\Contract\TransferableItemInterface;
use Gtt\SynchronizationPlugin\Entity\SynchronizationInterface;
use Gtt\SynchronizationPlugin\Processing\Client\SynchronizationClientInterface;
use Gtt\SynchronizationPlugin\Processing\Client\SynchronizationInputClientInterface;
use Symfony\Component\Serializer\Serializer;

class TestInputClient implements SynchronizationInputClientInterface
{
    public const CODE = 'TEST_CLIENT_CODE';

    public const TYPE = 'test_type';

    public const CRUD_TYPES = SynchronizationClientInterface::CRUD_TYPES_ALL;

    public function isSupported(ReceiveOperation $receiveOperation): bool
    {
        return true;
    }

    public function getOperationCode(): string
    {
        return static::CODE;
    }

    public function getType(): string
    {
        return static::TYPE;
    }

    public function getCrudTypes(): array
    {
        return static::CRUD_TYPES;
    }

    public function buildTransferableInput(ReceiveOperation $receiveOperation): ?TransferableInputInterface
    {
        return new class implements TransferableInputInterface {
            public function getOperationCode(): string
            {
                return 'test';
            }

            public function setOperationCode(string $operationCode): void{}

            public function getOperationId(): string
            {
                return 'test-id';
            }

            public function setOperationId(string $operationId): void {}

            public function getData(): array
            {
                return [$this->getTransferableItem()];
            }

            public function setData(array $data): void {}

            public function addToData(TransferableItemInterface $dto): void {}

            public function getFromData(string $id): ?TransferableItemInterface
            {
                return $this->getTransferableItem();
            }

            private function getTransferableItem(): TransferableItemInterface
            {
                return new class implements TransferableItemInterface {

                    public function getSyncId(): string
                    {
                        return 'test-sync-id';
                    }

                    public function setSyncId(string $syncId): void {}
                };
            }
        };
    }

    public function getTransferEnvelopDtoClass(): string
    {
        return 'test-envelop';
    }

    public function getTransferItemDtoClass(): string
    {
        return 'test-item';
    }

    public function synchronizeInput(SynchronizationInterface $synchronization, TransferableItemInterface $item): void
    {
    }

    public function getPreparedSerializer(): Serializer
    {
        return new Serializer();
    }
}
