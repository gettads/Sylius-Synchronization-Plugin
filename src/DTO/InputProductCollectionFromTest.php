<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\DTO;

use Gtt\SynchronizationPlugin\DTO\Contract\TransferableEnvelopTrait;
use Gtt\SynchronizationPlugin\DTO\Contract\TransferableInputInterface;

class InputProductCollectionFromTest implements TransferableInputInterface
{
    use TransferableEnvelopTrait;

    /**
     * @inheritDoc
     */
    public function __construct(string $operationId, string $operationCode, array $data)
    {
        $this->operationId = $operationId;
        $this->operationCode = $operationCode;
        $this->data = $data;
    }
}
