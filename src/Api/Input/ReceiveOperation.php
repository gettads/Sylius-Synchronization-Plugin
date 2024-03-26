<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\Api\Input;

class ReceiveOperation implements RawInputEnvelopInterface
{
    public const PROPERTIES = [
        self::PROPERTY_OPERATION_ID,
        self::PROPERTY_OPERATION_CODE,
        self::PROPERTY_DATA,
    ];

    public const PROPERTY_OPERATION_ID = 'operationId';
    public const PROPERTY_OPERATION_CODE = 'operationCode';
    public const PROPERTY_DATA = 'data';

    /**
     * $operationId is a public ID of synchronization procedure between current project and sync-server.
     * $operationCode is an alias of synchronization procedure.
     *
     * @param array<string|int, array|\ArrayObject|bool|float|int|string|null> $data
     */
    public function __construct(private string $operationId, private string $operationCode, private array $data)
    {
    }

    /**
     * @return array<string|int, array|\ArrayObject|bool|float|int|string|null>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array<string, array|\ArrayObject|bool|float|int|string|null> $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
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
