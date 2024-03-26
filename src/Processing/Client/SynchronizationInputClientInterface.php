<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\Processing\Client;

use Gtt\SynchronizationPlugin\Api\Input\ReceiveOperation;
use Gtt\SynchronizationPlugin\DTO\Contract\TransferableInputInterface;
use Gtt\SynchronizationPlugin\DTO\Contract\TransferableItemInterface;
use Gtt\SynchronizationPlugin\Entity\SynchronizationInterface;
use Gtt\SynchronizationPlugin\Exception\SynchronizationInvalidInputException;
use Symfony\Component\Serializer\Serializer;

interface SynchronizationInputClientInterface extends SynchronizationClientInterface
{
    public function isSupported(ReceiveOperation $receiveOperation): bool;

    /**
     * @throws SynchronizationInvalidInputException
     */
    public function buildTransferableInput(ReceiveOperation $receiveOperation): ?TransferableInputInterface;

    public function getTransferEnvelopDtoClass(): string;

    public function getTransferItemDtoClass(): string;

    public function synchronizeInput(SynchronizationInterface $synchronization, TransferableItemInterface $item): void;

    public function getPreparedSerializer(): Serializer;
}
