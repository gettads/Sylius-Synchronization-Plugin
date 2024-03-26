<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\DoctrineEventListener\DTO;

use Gtt\SynchronizationPlugin\Exception\SynchronizationCorruptedException;

class EntityChangeCollectionDto
{
    /**
     * @var array<int|string, EntityChangeDto>
     */
    private array $changes = [];

    public function __construct(private bool $isChronology)
    {
    }

    public function add(EntityChangeDto $changeDto, ?string $identifier = null): void
    {
        if (
            (
                $identifier !== null
                && $this->isChronology
            )
            || (
                $identifier === null
                && !$this->isChronology
            )
        ) {
            throw new SynchronizationCorruptedException(
                'Incorrect using mode. If $this->isChronology === true, $identifier must be NULL (and vice versa)'
            );
        }

        if ($identifier !== null) {
            $this->changes[$identifier] = $changeDto;

            return;
        }

        $this->changes[] = $changeDto;
    }

    /**
     * @return array<int|string, EntityChangeDto>
     */
    public function getChanges(): array
    {
        return $this->changes;
    }
}
