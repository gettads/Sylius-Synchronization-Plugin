<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\DoctrineEventListener\DTO;

use Sylius\Component\Resource\Model\ResourceInterface;

class EntityChangeDto
{
    public const CRUD_TYPE_CREATE = 'create';

    public const CRUD_TYPE_UPDATE = 'update';

    public const CRUD_TYPE_DELETE = 'delete';

    /**
     * @param array<string, array<int, int|string|float|bool|object|null>> $changes
     */
    public function __construct(
        private ResourceInterface $resource,
        private array $changes,
        private string $type,
    ) {
    }

    public function getResource(): ResourceInterface
    {
        return $this->resource;
    }

    public function setResource(ResourceInterface $resource): void
    {
        $this->resource = $resource;
    }

    /**
     * @return array<string, array<int, int|string|float|bool|object|null>>
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * @param array<string, array<int, int|string|float|bool|object|null>> $changes
     */
    public function setChanges(array $changes): void
    {
        $this->changes = $changes;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
