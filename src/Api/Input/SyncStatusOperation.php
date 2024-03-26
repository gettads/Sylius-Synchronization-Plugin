<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\Api\Input;

/**
 * @codeCoverageIgnore
 */
class SyncStatusOperation
{
    private ?string $operationId = null;

    private ?string $syncId = null;

    private ?string $status = null;

    private ?string $errorMessage = null;

    public function getOperationId(): ?string
    {
        return $this->operationId;
    }

    public function setOperationId(?string $operationId): void
    {
        $this->operationId = $operationId;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
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
