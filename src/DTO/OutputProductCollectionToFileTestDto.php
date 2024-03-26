<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\DTO;

use Gtt\SynchronizationPlugin\DTO\Contract\TransferableEnvelopTrait;
use Gtt\SynchronizationPlugin\DTO\Contract\TransferableOutputInterface;

class OutputProductCollectionToFileTestDto implements TransferableOutputInterface
{
    use TransferableEnvelopTrait;

    /**
     * @inheritDoc
     */
    public function __construct(string $operationId, string $operationCode, array $data = [])
    {
        $this->operationId = $operationId;
        $this->operationCode = $operationCode;
        $this->data = $data;
    }
}
