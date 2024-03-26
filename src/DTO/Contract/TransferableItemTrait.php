<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\DTO\Contract;

/**
 * @codeCoverageIgnore
 */
trait TransferableItemTrait
{
    /**
     * $syncId is a public ID unique per object type used for data exchange
     */
    public function __construct(private string $syncId)
    {
    }

    public function getSyncId(): string
    {
        return $this->syncId;
    }

    public function setSyncId(string $syncId): void
    {
        $this->syncId = $syncId;
    }
}
