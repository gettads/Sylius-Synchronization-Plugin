<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\Processing\Client;

use Gtt\SynchronizationPlugin\DoctrineEventListener\DTO\EntityChangeCollectionDto;
use Gtt\SynchronizationPlugin\DTO\Contract\TransferableOutputInterface;
use Gtt\SynchronizationPlugin\Entity\SynchronizationInterface;
use Gtt\SynchronizationPlugin\Exception\SynchronizationInvalidOutputException;
use Symfony\Component\Serializer\Serializer;

interface SynchronizationOutputClientInterface extends SynchronizationClientInterface
{
    public function isSupported(EntityChangeCollectionDto $dto): bool;

    /**
     * @throws SynchronizationInvalidOutputException
     */
    public function buildTransferableOutput(
        EntityChangeCollectionDto $appliedOnlyChanges,
        EntityChangeCollectionDto $allChronologyChanges,
        SynchronizationInterface $synchronization,
    ): ?TransferableOutputInterface;

    public function synchronizeOutput(TransferableOutputInterface $dto): void;

    public function getTransferEnvelopDtoClass(): string;

    public function getTransferItemDtoClass(): string;

    public function getPreparedSerializer(): Serializer;
}
