<?php

declare(strict_types=1);

namespace Tests\Gtt\SynchronizationPlugin\Application\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gtt\SynchronizationPlugin\Contract\Attribute\AsSyncableEntity;
use Gtt\SynchronizationPlugin\Contract\SyncableEntityInterface;
use Gtt\SynchronizationPlugin\Contract\SyncableEntityTrait;
use Sylius\Component\Resource\Model\ResourceInterface;
use Tests\Gtt\SynchronizationPlugin\Application\Synchronization\Processing\TestOutputClient;

#[AsSyncableEntity(operations: [AsSyncableEntity::ROOT_PATH => [TestOutputClient::CODE]])]
#[ORM\Entity]
#[ORM\Table(name: 'sylius_test_sync_entity_first')]
class TestSyncableEntityFirst implements ResourceInterface, SyncableEntityInterface
{
    public const DEFAULT_ID = 1;

    use SyncableEntityTrait;

    private ?int $id = self::DEFAULT_ID;

    #[ORM\OneToMany(targetEntity: TestSyncableEntitySecond::class, mappedBy: 'first')]
    protected Collection $seconds;

    public function __construct()
    {
        $this->seconds = new ArrayCollection();
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeconds(): Collection
    {
        return $this->seconds;
    }

    public function setSeconds(array $seconds): void
    {
        foreach ($seconds as $second) {
            $this->seconds->add($second);
        }
    }
}
