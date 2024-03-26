<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\DTO\Contract;

interface TransferableEnvelopInterface
{
    public function getOperationCode(): string;

    public function setOperationCode(string $operationCode): void;

    public function getOperationId(): string;

    public function setOperationId(string $operationId): void;

    /**
     * @return array<TransferableItemInterface>
     */
    public function getData(): array;

    /**
     * @param array<TransferableItemInterface> $data
     */
    public function setData(array $data): void;

    public function addToData(TransferableItemInterface $dto): void;

    public function getFromData(string $id): ?TransferableItemInterface;
}
