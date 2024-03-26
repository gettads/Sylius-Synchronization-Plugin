<?php

declare(strict_types=1);

namespace Tests\Gtt\SynchronizationPlugin\Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gtt\SynchronizationPlugin\Contract\Attribute\AsSyncableEntity;
use Sylius\Component\Resource\Model\ResourceInterface;
use Tests\Gtt\SynchronizationPlugin\Application\Synchronization\Processing\TestOutputClient;

#[AsSyncableEntity(operations: ['first' => [TestOutputClient::CODE]])]
#[ORM\Entity]
#[ORM\Table(name: 'sylius_test_sync_entity_second')]
class TestSyncableEntitySecond implements ResourceInterface
{
    private int $id = 1;

    #[ORM\ManyToOne(targetEntity: TestSyncableEntityFirst::class, inversedBy: 'seconds')]
    private TestSyncableEntityFirst $first;

    public function getId():int
    {
        return $this->id;
    }

    public function getFirst(): TestSyncableEntityFirst
    {
        return $this->first;
    }

    public function setFirst(TestSyncableEntityFirst $first): void
    {
        $this->first = $first;
    }
}
