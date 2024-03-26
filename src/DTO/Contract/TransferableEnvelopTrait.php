<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\DTO\Contract;

/**
 * @codeCoverageIgnore
 */
trait TransferableEnvelopTrait
{
    /**
     * $operationId is a public ID of synchronization procedure between current project and sync-server.
     * $operationCode is an alias of synchronization procedure.
     * - For outgoing flow: it is used in AsSyncableEntity attribute as operation code,
     *       and by this one will be applied any instance of SynchronizationOutputClientInterface
     * - For incoming flow: by this one will be applied any instance of SynchronizationInputClientInterface
     *
     * @param array<TransferableItemInterface> $data
     */
    public function __construct(private string $operationId, private string $operationCode, private array $data)
    {
    }

    /**
     * @return array<TransferableItemInterface>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array<TransferableItemInterface> $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function addToData(TransferableItemInterface $dto): void
    {
        $this->data[$dto->getSyncId()] = $dto;
    }

    public function getFromData(string $syncId): ?TransferableItemInterface
    {
        return $this->data[$syncId] ?? null;
    }

    public function getOperationId(): string
    {
        return $this->operationId;
    }

    public function setOperationId(string $operationId): void
    {
        $this->operationId = $operationId;
    }

    public function getOperationCode(): string
    {
        return $this->operationCode;
    }

    public function setOperationCode(string $operationCode): void
    {
        $this->operationCode = $operationCode;
    }
}
