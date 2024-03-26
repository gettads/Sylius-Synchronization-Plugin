<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\Processing\Client;

use Gtt\SynchronizationPlugin\Api\Input\ReceiveOperation;
use Gtt\SynchronizationPlugin\DTO\Contract\TransferableInputInterface;
use Gtt\SynchronizationPlugin\DTO\Contract\TransferableItemInterface;
use Gtt\SynchronizationPlugin\DTO\InputProductCollectionFromTest;
use Gtt\SynchronizationPlugin\DTO\InputProductFromTestDto;
use Gtt\SynchronizationPlugin\Entity\SynchronizationInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

class ProductFromApiTestClient extends BaseSynchronizationInputClient implements SynchronizationInputClientInterface
{
    public const CODE = 'PRODUCT_RECIEVE_TEST';

    public const TYPE = self::TYPE_PRODUCT;

    public const CRUD_TYPES = SynchronizationClientInterface::CRUD_TYPES_ALL;

    public function getTransferEnvelopDtoClass(): string
    {
        return InputProductCollectionFromTest::class;
    }

    public function getTransferItemDtoClass(): string
    {
        return InputProductFromTestDto::class;
    }

    public function buildTransferableInput(ReceiveOperation $receiveOperation): ?TransferableInputInterface
    {
        return parent::buildTransferableInput($receiveOperation);
    }

    public function synchronizeInput(SynchronizationInterface $synchronization, TransferableItemInterface $item): void
    {
        $this->logger->info(
            'Operation id:' . $synchronization->getOperationId() . ' logging transfer: ' .
            $this->serializer->serialize($item, JsonEncoder::FORMAT)
        );
    }
}
