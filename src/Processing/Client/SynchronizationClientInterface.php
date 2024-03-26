<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\Processing\Client;

interface SynchronizationClientInterface
{
    public const TYPE_PRODUCT = 'products';
    public const TYPE_ORDER = 'orders';
    public const TYPE_ATTRIBUTES = 'attributes';
    public const TYPE_CATEGORY = 'categories';
    public const TYPE_CUSTOMER = 'customers';

    public const CRUD_TYPE_CREATE = 'create';
    public const CRUD_TYPE_UPDATE = 'update';
    public const CRUD_TYPE_DELETE = 'delete';

    public const CRUD_TYPES_ALL = [self::CRUD_TYPE_CREATE, self::CRUD_TYPE_UPDATE, self::CRUD_TYPE_DELETE];

    public function getOperationCode(): string;

    public function getType(): string;

    /**
     * @return array<string>
     */
    public function getCrudTypes(): array;
}
