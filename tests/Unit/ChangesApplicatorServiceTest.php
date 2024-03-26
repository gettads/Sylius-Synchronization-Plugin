<?php

declare(strict_types=1);

namespace Tests\Gtt\SynchronizationPlugin\Unit;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use PHPUnit\Framework\TestCase;
use Gtt\SynchronizationPlugin\DoctrineEventListener\DTO\EntityChangeCollectionDto;
use Gtt\SynchronizationPlugin\DoctrineEventListener\DTO\EntityChangeDto;
use Gtt\SynchronizationPlugin\DoctrineEventListener\Service\ChangesApplicatorService;
use Gtt\SynchronizationPlugin\DoctrineService\SynchronizationAttributeDataExtractor;
use Psr\Log\LoggerInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Tests\Gtt\SynchronizationPlugin\Application\Entity\TestSyncableEntityFirst;
use Tests\Gtt\SynchronizationPlugin\Application\Entity\TestSyncableEntitySecond;

final class ChangesApplicatorServiceTest extends TestCase
{
    private ChangesApplicatorService $applicator;

    protected function setUp(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $emMock = $this->createMock(EntityManagerInterface::class);
        $driverMock = $this->createConfiguredMock(MappingDriverChain::class, [
            'getAllClassNames' => [TestSyncableEntityFirst::class, TestSyncableEntitySecond::class],
        ]);
        $extractor = new SynchronizationAttributeDataExtractor($emMock, $driverMock, 'test');

        $this->applicator = new ChangesApplicatorService(
            $logger,
            $extractor,
            new EntityChangeCollectionDto(true),
            new EntityChangeCollectionDto(false),
        );
    }

    /**
     * @test
     */
    public function I_check_apply_changes_positive(): void
    {
        $changesMap = ['id' => [0 => 1, 1 => 111]];

        $changeDto = new EntityChangeDto(new TestSyncableEntityFirst(), $changesMap, EntityChangeDto::CRUD_TYPE_UPDATE);
        $this->applicator->applyChanges($changeDto);
        $this->assertTrue($this->applicator->hasChanges());

        $changeDto = new EntityChangeDto(new TestSyncableEntitySecond(), $changesMap, EntityChangeDto::CRUD_TYPE_UPDATE);
        $this->applicator->applyChanges($changeDto);
        $this->assertTrue($this->applicator->hasChanges());
    }

    /**
     * @test
     */
    public function I_check_apply_changes_without_changes_positive(): void
    {
        $changesMap = ['id' => [0 => 1, 1 => 111]];

        $changeDto = new EntityChangeDto(new TestSyncableEntityFirst(), $changesMap, EntityChangeDto::CRUD_TYPE_UPDATE);
        $this->applicator->applyChanges($changeDto);
        $this->assertTrue($this->applicator->hasChanges());

        $this->applicator->refresh();

        $changesMap = ['id' => [1, 111], 'updatedAt' => [null, time()]];
        $changeDto = new EntityChangeDto(new TestSyncableEntityFirst(), $changesMap, EntityChangeDto::CRUD_TYPE_UPDATE);
        $this->applicator->applyChanges($changeDto);
        $this->assertFalse($this->applicator->hasChanges());
    }

    /**
     * @test
     */
    public function I_check_apply_changes_on_delete_positive(): void
    {
        $first = new TestSyncableEntityFirst();
        $second = new TestSyncableEntitySecond();
        $second->setFirst($first);
        $first->setSeconds([$second]);

        $this->applicator->applyChanges(new EntityChangeDto($second, [], EntityChangeDto::CRUD_TYPE_DELETE));
        unset($second);
        $this->assertTrue($this->applicator->hasChanges());

        $changeDto = current($this->applicator->getOnlyAppliedChanges()->getChanges());
        assert($changeDto instanceof EntityChangeDto);
        $this->assertEquals(['id' => [1, null]], $changeDto->getChanges());

        $secondResource = $changeDto->getResource();
        assert($secondResource instanceof TestSyncableEntitySecond);
        $this->assertTrue($secondResource->getFirst()->getId() === 1);

        $this->applicator->applyChanges(new EntityChangeDto($first, [], EntityChangeDto::CRUD_TYPE_DELETE));
        unset($first);
        $changeDto = current($this->applicator->getOnlyAppliedChanges()->getChanges());
        assert($changeDto instanceof EntityChangeDto);
        $this->assertEquals($changeDto->getChanges(), ['id' => [1, null]]);
    }

    /**
     * @test
     */
    public function I_check_apply_changes_on_bad_entity_negative(): void
    {
        $noAnnotation = new class implements ResourceInterface {
            public function getId(): int
            {
                return 1;
            }
        };

        $this->applicator->applyChanges(new EntityChangeDto($noAnnotation, [], EntityChangeDto::CRUD_TYPE_CREATE));

        $this->assertFalse($this->applicator->hasChanges());
    }

    /**
     * @test
     */
    public function I_check_refresh_positive(): void
    {
        $changesMap = ['id' => [0 => 1, 1 => 111]];
        $changeDto = new EntityChangeDto(new TestSyncableEntityFirst(), $changesMap, EntityChangeDto::CRUD_TYPE_UPDATE);

        $this->applicator->applyChanges($changeDto);
        $this->assertTrue($this->applicator->getOnlyAppliedChanges()->getChanges() !== []);

        $this->applicator->refresh();
        $this->applicator->applyChanges($changeDto);
        $this->assertTrue($this->applicator->getOnlyAppliedChanges()->getChanges() === []);

        $this->applicator->refresh(true);
        $this->applicator->applyChanges($changeDto);
        $this->assertTrue($this->applicator->getOnlyAppliedChanges()->getChanges() !== []);

        $this->assertEquals(3, count($this->applicator->getAllChronologyChanges()->getChanges()));
    }

    /**
     * @test
     */
    public function I_check_prepare_valid_changes_positive(): void
    {
        $entity = new TestSyncableEntityFirst();

        $unitOfWorkMock = $this->createConfiguredMock(UnitOfWork::class, [
            'getEntityChangeSet' => ['id' => [1, 111]],
        ]);

        $this->assertEquals(
            ['id' => [1, 111]],
            $this->applicator->prepareValidChanges($unitOfWorkMock, $entity),
        );
    }

    /**
     * @test
     */
    public function I_check_prepare_valid_changes_negative_changeset(): void
    {
        $entity = new TestSyncableEntityFirst();

        $unitOfWorkMock = $this->createConfiguredMock(UnitOfWork::class, ['getEntityChangeSet' => [1 => [1, 111]]]);
        $message = 'Can not prepare valid data: key is not a property of entity';
        $this->assertEquals([], $this->applicator->prepareValidChanges($unitOfWorkMock, $entity), $message);

        $unitOfWorkMock = $this->createConfiguredMock(UnitOfWork::class, [
            'getEntityChangeSet' => ['id' => new class {}],
        ]);
        $message = 'Can not prepare valid data: value is object';
        $this->assertEquals([], $this->applicator->prepareValidChanges($unitOfWorkMock, $entity), $message);

        $unitOfWorkMock = $this->createConfiguredMock(UnitOfWork::class, [
            'getEntityChangeSet' => ['id' => ['foo' => 'foo', 'bar' => 'bar']],
        ]);
        $message = 'Can not prepare valid data: value does not contain keys 0 and 1';
        $this->assertEquals([], $this->applicator->prepareValidChanges($unitOfWorkMock, $entity), $message);
    }
}
