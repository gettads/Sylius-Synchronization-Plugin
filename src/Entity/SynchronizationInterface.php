<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\Entity;

use ArrayObject;
use Gtt\SynchronizationPlugin\DTO\Contract\TransferableItemInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

interface SynchronizationInterface extends ResourceInterface
{
    public const FLOW_TYPE_INCOMING = 'incoming';
    public const FLOW_TYPE_OUTCOMING = 'outcoming';

    public const STATUS_BEFORE_SYNC = 'before_sync';
    public const STATUS_ERROR_ON_SYNC_MAPPING = 'error_on_sync_mapping';
    public const STATUS_ERROR_ON_SYNC_TRANSPORT = 'error_on_sync_transport';
    public const STATUS_SYNCHRONIZATION = 'sync_in_progress';
    public const STATUS_ERROR_SYNC = 'sync_error';
    public const STATUS_SUCCESS_SYNC = 'sync_ok';
    public const STATUSES = [
        self::STATUS_BEFORE_SYNC,
        self::STATUS_ERROR_ON_SYNC_MAPPING,
        self::STATUS_ERROR_ON_SYNC_TRANSPORT,
        self::STATUS_SYNCHRONIZATION,
        self::STATUS_ERROR_SYNC,
        self::STATUS_SUCCESS_SYNC,
    ];

    public function getId(): ?int;

    public function setId(?int $id): void;

    public function getType(): string;

    public function setType(string $type): void;

    public function getFlowType(): string;

    public function setFlowType(string $flowType): void;

    public function getOperationCode(): ?string;

    public function setOperationCode(?string $operationCode): void;

    public function getOperationId(): ?string;

    public function setOperationId(?string $operationId): void;

    public function getSyncId(): ?string;

    public function setSyncId(?string $syncId): void;

    /**
     * @return array<TransferableItemInterface>|array<string, array|ArrayObject|bool|float|int|string|null>
     */
    public function getPayload(): array;

    /**
     * @param array<TransferableItemInterface>|array<string, array|ArrayObject|bool|float|int|string|null> $payload
     */
    public function setPayload(array $payload): void;

    public function getStatus(): string;

    public function setStatus(string $status): void;

    public function getErrorMessage(): ?string;

    public function setErrorMessage(?string $errorMessage): void;
}
