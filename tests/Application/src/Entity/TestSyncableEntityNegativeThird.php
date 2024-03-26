<?php

declare(strict_types=1);

namespace Tests\Gtt\SynchronizationPlugin\Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gtt\SynchronizationPlugin\Contract\Attribute\AsSyncableEntity;
use Sylius\Component\Resource\Model\ResourceInterface;
use Tests\Gtt\SynchronizationPlugin\Application\Synchronization\Processing\TestOutputClient;

#[AsSyncableEntity(operations: ['error' => [self::BAD_CODE], 'first.errorObj' => [self::BAD_CODE]])]
#[ORM\Entity]
#[ORM\Table(name: 'sylius_test_sync_entity_second')]
class TestSyncableEntityNegativeThird implements ResourceInterface
{
    public const BAD_CODE = 'bad_code';

    private int $id = 1;

    #[ORM\ManyToOne(targetEntity: TestSyncableEntityNegativeFirst::class, inversedBy: 'seconds')]
    private TestSyncableEntityNegativeFirst $first;

    public function getId():int
    {
        return $this->id;
    }

    public function getFirst(): TestSyncableEntityNegativeFirst
    {
        return $this->first;
    }

    public function setFirst(TestSyncableEntityNegativeFirst $first): void
    {
        $this->first = $first;
    }

    public function getError(): string
    {
        return 'string_is_bad_type_for_relation';
    }
}
