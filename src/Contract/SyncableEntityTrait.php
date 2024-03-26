<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\Contract;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gtt\SynchronizationPlugin\Entity\SynchronizationInterface;

/**
 * @codeCoverageIgnore
 */
trait SyncableEntityTrait
{
    /**
     * Can not have relations 1:M (reason: one sync table for many entities). Will be set by syncId and operationCode.
     *
     * @var array<SynchronizationInterface>
     */
    private array $synchronizations = [];

    #[ORM\Column(name: 'sync_id', type: Types::STRING, length: 255, nullable: true)]
    private ?string $syncId = null;

    /**
     * @return array<SynchronizationInterface>
     */
    public function getSynchronizations(): array
    {
        return $this->synchronizations;
    }

    /**
     * @param array<SynchronizationInterface> $synchronizations
     */
    public function setSynchronizations(array $synchronizations): void
    {
        $this->synchronizations = $synchronizations;
    }

    public function getSyncId(): ?string
    {
        return $this->syncId;
    }

    public function setSyncId(?string $syncId): void
    {
        $this->syncId = $syncId;
    }
}
