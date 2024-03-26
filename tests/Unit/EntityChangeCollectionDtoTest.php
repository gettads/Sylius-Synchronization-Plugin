<?php

declare(strict_types=1);

namespace Tests\Gtt\SynchronizationPlugin\Unit;

use PHPUnit\Framework\TestCase;
use Gtt\SynchronizationPlugin\DoctrineEventListener\DTO\EntityChangeCollectionDto;
use Gtt\SynchronizationPlugin\DoctrineEventListener\DTO\EntityChangeDto;
use Gtt\SynchronizationPlugin\Exception\SynchronizationCorruptedException;
use Tests\Gtt\SynchronizationPlugin\Application\Entity\TestSyncableEntityFirst;

class EntityChangeCollectionDtoTest extends TestCase
{
    /**
     * @test
     */
    public function I_check_add_positive(): void
    {
        $change = new EntityChangeDto(new TestSyncableEntityFirst(), [], EntityChangeDto::CRUD_TYPE_CREATE);
        $collection = new EntityChangeCollectionDto(false);

        $collection->add($change, 'test-identifier-1');

        $this->assertEquals(1, count($collection->getChanges()));
    }

    /**
     * @test
     */
    public function I_check_add_negative(): void
    {
        $change = new EntityChangeDto(new TestSyncableEntityFirst(), [], EntityChangeDto::CRUD_TYPE_CREATE);
        $collection = new EntityChangeCollectionDto(false);

        $this->expectException(SynchronizationCorruptedException::class);
        $collection->add($change);
    }
}
