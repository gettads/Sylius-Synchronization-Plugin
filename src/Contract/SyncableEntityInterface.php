<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\Contract;

use Gtt\SynchronizationPlugin\Entity\SynchronizationInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

interface SyncableEntityInterface extends ResourceInterface
{
    /**
     * @return array<SynchronizationInterface>
     */
    public function getSynchronizations(): array;

    /**
     * @param array<SynchronizationInterface> $synchronizations
     */
    public function setSynchronizations(array $synchronizations): void;

    /**
     * @return string SyncID - immutable entity's identifier from 3rd part service (or inside current project)
     */
    public function getSyncId(): ?string;

    public function setSyncId(?string $syncId): void;
}
