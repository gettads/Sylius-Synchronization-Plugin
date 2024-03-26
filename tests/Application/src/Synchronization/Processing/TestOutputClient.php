<?php

declare(strict_types=1);

namespace Tests\Gtt\SynchronizationPlugin\Application\Synchronization\Processing;

use Gtt\SynchronizationPlugin\DoctrineEventListener\DTO\EntityChangeCollectionDto;
use Gtt\SynchronizationPlugin\DTO\Contract\TransferableItemInterface;
use Gtt\SynchronizationPlugin\DTO\Contract\TransferableOutputInterface;
use Gtt\SynchronizationPlugin\Entity\SynchronizationInterface;
use Gtt\SynchronizationPlugin\Exception\SynchronizationInvalidOutputException;
use Gtt\SynchronizationPlugin\Processing\Client\SynchronizationClientInterface;
use Gtt\SynchronizationPlugin\Processing\Client\SynchronizationOutputClientInterface;
use Symfony\Component\Serializer\Serializer;

class TestOutputClient implements SynchronizationOutputClientInterface
{
    public const CODE = 'TEST_CLIENT_CODE';

    public const TYPE = 'test_type';

    public const CRUD_TYPES = SynchronizationClientInterface::CRUD_TYPES_ALL;

    public function isSupported(EntityChangeCollectionDto $dto): bool
    {
        return true;
    }

    public function buildTransferableOutput(
        EntityChangeCollectionDto $appliedOnlyChanges,
        EntityChangeCollectionDto $allChronologyChanges,
        SynchronizationInterface $synchronization,
    ): ?TransferableOutputInterface
    {
        return new class implements TransferableOutputInterface {

            public function getOperationCode(): string
            {
                return 'test';
            }

            public function setOperationCode(string $operationCode): void
            {
            }

            public function getOperationId(): string
            {
                return 'test-operation-id';
            }

            public function setOperationId(string $operationId): void
            {
            }

            public function getData(): array
            {
                return [];
            }

            public function setData(array $data): void
            {
            }

            public function addToData(TransferableItemInterface $dto): void
            {
            }

            public function getFromData(string $id): ?TransferableItemInterface
            {
                return new class implements TransferableItemInterface {

                    public function getSyncId(): string
                    {
                        return 'test-sync-id';
                    }

                    public function setSyncId(string $syncId): void
                    {
                    }
                };
            }
        };
    }

    public function synchronizeOutput(TransferableOutputInterface $dto): void
    {
    }

    public function getTransferEnvelopDtoClass(): string
    {
        return '';
    }

    public function getTransferItemDtoClass(): string
    {
        return '';
    }

    public function getPreparedSerializer(): Serializer
    {
        new Serializer();
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
}
