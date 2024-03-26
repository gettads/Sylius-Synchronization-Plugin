<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\DTO\Contract;

interface TransferableItemInterface
{
    public function getSyncId(): string;

    public function setSyncId(string $syncId): void;
}
