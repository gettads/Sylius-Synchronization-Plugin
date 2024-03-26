<?php

declare(strict_types=1);

namespace Tests\Gtt\SynchronizationPlugin\Unit;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Gtt\SynchronizationPlugin\Contract\Attribute\AsSyncableEntity;
use Gtt\SynchronizationPlugin\Contract\SyncableEntityInterface;
use Gtt\SynchronizationPlugin\DoctrineService\SynchronizationAttributeDataExtractor;
use Gtt\SynchronizationPlugin\Entity\Synchronization;
use Gtt\SynchronizationPlugin\Exception\SynchronizationConfigException;
use Sylius\Component\Resource\Model\ResourceInterface;
use Tests\Gtt\SynchronizationPlugin\Application\Entity\TestSyncableEntityFirst;
use Tests\Gtt\SynchronizationPlugin\Application\Entity\TestSyncableEntityNegativeFirst;
use Tests\Gtt\SynchronizationPlugin\Application\Entity\TestSyncableEntityNegativeSecond;
use Tests\Gtt\SynchronizationPlugin\Application\Entity\TestSyncableEntityNegativeThird;
use Tests\Gtt\SynchronizationPlugin\Application\Entity\TestSyncableEntitySecond;
use Tests\Gtt\SynchronizationPlugin\Application\Synchronization\Processing\TestOutputClient;

final class SynchronizationAttributeDataExtractorTest extends TestCase
{
    public MockObject&TestOutputClient $clientOutputMock;

    public TestSyncableEntityFirst $entityFirst;

    public TestSyncableEntitySecond $entitySecond;

    public TestSyncableEntityNegativeFirst $entityNegativeFirst;

    public TestSyncableEntityNegativeSecond $entityNegativeSecond;

    public TestSyncableEntityNegativeThird $entityNegativeThird;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTestEntities();
    }

    private function getOutputClient(): MockObject & TestOutputClient
    {
        if (!isset($this->clientOutputMock)) {
            $this->clientOutputMock = $this->createConfiguredMock(
                TestOutputClient::class,
                [
                    'getOperationCode' => TestOutputClient::CODE,
                    'getType' => TestOutputClient::TYPE,
                    'getCrudTypes' => TestOutputClient::CRUD_TYPES,
                ],
            );
        }
        return $this->clientOutputMock;
    }

    private function setUpTestEntities(): void
    {
        $this->entityFirst = new TestSyncableEntityFirst();
        $this->entitySecond = new TestSyncableEntitySecond();
        $this->entitySecond->setFirst($this->entityFirst);
        $this->entityFirst->setSeconds([$this->entitySecond]);

        $this->entityNegativeFirst = new TestSyncableEntityNegativeFirst();
        $this->entityNegativeSecond = new TestSyncableEntityNegativeSecond();
        $this->entityNegativeSecond->setFirst($this->entityNegativeFirst);
        $this->entityNegativeFirst->setSeconds([$this->entityNegativeSecond]);

        $this->entityNegativeThird = new TestSyncableEntityNegativeThird();
    }

    /**
     * @test
     */
    public function I_can_get_syncable_entity(): void
    {
        $extractor = $this->createExtractor();
        $syncableEntity = $extractor->getSyncableEntity($this->entityFirst, $this->getOutputClient());
        $this->assertEquals($syncableEntity::class, $this->entityFirst::class);

        $syncableEntity = $extractor->getSyncableEntity($this->entitySecond, $this->getOutputClient());
        $this->assertEquals($syncableEntity::class, $this->entityFirst::class);

        $syncableEntity = $extractor->getSyncableEntity($this->entitySecond, $this->getOutputClient());
        $this->assertEquals($syncableEntity::class, $this->entityFirst::class);
    }

    /**
     * @test
     */
    public function I_can_not_get_syncable_entity_on_not_syncable_target_root(): void
    {
        $extractor = $this->createExtractor();
        $this->expectException(SynchronizationConfigException::class);
        $syncableEntity = $extractor->getSyncableEntity($this->entityNegativeFirst, $this->getOutputClient());
    }

    /**
     * @test
     */
    public function I_can_not_get_syncable_entity_on_bad_accessor(): void
    {
        $extractor = $this->createExtractor();

        $result = $extractor->getSyncableEntity($this->entityNegativeThird, $this->getOutputClient());
        $this->assertEquals($result, null, 'Invalid code for client is here.');

        $this->expectException(SynchronizationConfigException::class);
        $syncableEntity = $extractor->getSyncableEntity($this->entityNegativeSecond, $this->getOutputClient());
    }

    /**
     * @test
     */
    public function I_can_not_get_syncable_entity_on_not_object_relation(): void
    {
        $extractor = $this->createExtractor();
        $client = $this->createConfiguredMock(TestOutputClient::class, [
            'getOperationCode' => TestSyncableEntityNegativeThird::BAD_CODE
        ]);

        $this->expectException(SynchronizationConfigException::class);
        $result = $extractor->getSyncableEntity($this->entityNegativeThird, $client);
    }

    private function createExtractor(?array $params = null): SynchronizationAttributeDataExtractor
    {
        if ($params === null) {
            return new class extends SynchronizationAttributeDataExtractor
            {
                public function __construct()
                {
                }
            };
        }

        return new SynchronizationAttributeDataExtractor(...$params);
    }

    /**
     * @test
     */
    public function I_can_get_relations_names_to_syncables(): void
    {
        $extractor = $this->createExtractor();
        $result = $extractor->getRelationsNamesToSyncables($this->entityFirst);
        $this->assertEquals($result, [AsSyncableEntity::ROOT_PATH]);

        $result = $extractor->getRelationsNamesToSyncables($this->entitySecond);
        $this->assertEquals($result, ['first']);

        $result = $extractor->getRelationsNamesToSyncables(new Synchronization());
        $this->assertEquals($result, [], 'Synchronization dosen\'t have AsSyncableEntity attribute.');
    }

    /**
     * @test
     */
    public function I_can_get_extract_operation_codes(): void
    {
        $extractor = $this->createExtractor();
        $codes = $extractor->extractOperationCodes($this->entityFirst);
        $this->assertEquals([TestOutputClient::CODE], $codes);

        $codes = $extractor->extractOperationCodes($this->entitySecond);
        $this->assertEquals([TestOutputClient::CODE], $codes);
    }

    /**
     * @test
     */
    public function I_can_not_get_extract_operation_codes(): void
    {
        $extractor = $this->createExtractor();
        $object = new class implements ResourceInterface
        {
            public function __construct()
            {
            }

            public function getId(): int
            {
                return 1;
            }
        };
        $codes = $extractor->extractOperationCodes($object);
        $this->assertEquals([], $codes);
    }

    /**
     * @test
     */
    public function I_check_positive_entities_configuration(): void
    {
        $whiteList = [
            TestSyncableEntityFirst::class,
            TestSyncableEntitySecond::class,
        ];

        $metadataInfoMock = $this->createConfiguredMock(ClassMetadataInfo::class, [
            'getAssociationTargetClass' => TestSyncableEntityFirst::class,
        ]);

        $emMock = $this->createConfiguredMock(EntityManagerInterface::class, [
            'getClassMetadata' => $metadataInfoMock,
        ]);

        $driverMock = $this->createConfiguredMock(MappingDriverChain::class, [
            'getAllClassNames' => $whiteList,
        ]);

        $extractor = $this->createExtractor([$emMock, $driverMock, 'test']);
        $this->assertTrue($extractor->checkEntitiesConfigurations([TestOutputClient::CODE]));
    }

    /**
     * @test
     */
    public function I_check_negative_entities_configuration(): void
    {
        $metadataInfoMock = $this->createConfiguredMock(ClassMetadataInfo::class, [
            'getAssociationTargetClass' => TestSyncableEntityFirst::class,
        ]);
        $emMock = $this->createConfiguredMock(EntityManagerInterface::class, [
            'getClassMetadata' => $metadataInfoMock,
        ]);

        $driverMock = $this->createConfiguredMock(MappingDriverChain::class, [
            'getAllClassNames' => [TestSyncableEntityNegativeFirst::class],
        ]);
        $extractor = $this->createExtractor([$emMock, $driverMock, 'test']);
        try {
            $extractor->checkEntitiesConfigurations([TestOutputClient::CODE]);
        } catch (SynchronizationConfigException $exception) {
            $this->assertTrue(str_contains(
                $exception->getMessage(),
                'TestSyncableEntityNegativeFirst is not SyncableEntityInterface, but has path "."',
            ));
        }

        $driverMock = $this->createConfiguredMock(MappingDriverChain::class, [
            'getAllClassNames' => [TestSyncableEntityNegativeSecond::class],
        ]);
        $extractor = $this->createExtractor([$emMock, $driverMock, 'test']);
        try {
            $extractor->checkEntitiesConfigurations([TestOutputClient::CODE]);
        } catch (SynchronizationConfigException $exception) {
            $this->assertTrue(str_contains(
                $exception->getMessage(),
                'TestSyncableEntityNegativeSecond does not contain method',
            ));
        }

        $driverMock = $this->createConfiguredMock(MappingDriverChain::class, [
            'getAllClassNames' => [TestSyncableEntityNegativeThird::class],
        ]);
        $extractor = $this->createExtractor([$emMock, $driverMock, 'test']);
        try {
            $extractor->checkEntitiesConfigurations([TestOutputClient::CODE]);
        } catch (SynchronizationConfigException $exception) {
            $this->assertTrue(str_contains(
                $exception->getMessage(),
                'TestSyncableEntityNegativeThird has invalid operations codes',
            ));
        }

        $emMock = $this->createConfiguredMock(EntityManagerInterface::class, [
            'getClassMetadata' => [],
        ]);
        $driverMock = $this->createConfiguredMock(MappingDriverChain::class, [
            'getAllClassNames' => [TestSyncableEntityNegativeThird::class],
        ]);
        $extractor = $this->createExtractor([$emMock, $driverMock, 'test']);
        try {
            $extractor->checkEntitiesConfigurations([TestOutputClient::CODE]);
        } catch (SynchronizationConfigException $exception) {
            $this->assertTrue(str_contains(
                $exception->getMessage(),
                'TestSyncableEntityNegativeThird class has error with relation',
            ));
        }
    }

    /**
     * @test
     */
    public function I_can_get_has_syncable_attribute_annotation(): void
    {
        $extractor = $this->createExtractor();
        $this->assertEquals(true, $extractor->hasSyncableAttributeAnnotation($this->entityFirst));
        $this->assertEquals(true, $extractor->hasSyncableAttributeAnnotation($this->entitySecond));
    }

    /**
     * @test
     */
    public function I_can_get_relations_to_syncables(): void
    {
        $extractor = $this->createExtractor();
        $result = $extractor->getRelationsToSyncables($this->entityFirst);
        $this->assertEquals($result, []);

        $result = $extractor->getRelationsToSyncables($this->entitySecond);
        $preparedResult = [array_key_first($result) => get_class(current($result))];
        $this->assertEquals($preparedResult, ['first' => TestSyncableEntityFirst::class]);
    }

    /**
     * @test
     */
    public function I_can_not_get_relations_to_syncables_from_not_syncable(): void
    {
        $extractor = $this->createExtractor();

        $this->expectException(SynchronizationConfigException::class);
        $result = $extractor->getRelationsToSyncables($this->entityNegativeFirst);
    }

    /**
     * @test
     */
    public function I_can_not_get_relations_to_syncables_by_bad_path_relation(): void
    {
        $extractor = $this->createExtractor();

        $this->expectException(SynchronizationConfigException::class);
        $result = $extractor->getRelationsToSyncables($this->entityNegativeSecond);
    }

    /**
     * @test
     */
    public function I_can_not_get_relations_to_syncables_by_not_object_relation(): void
    {
        $extractor = $this->createExtractor();

        $this->expectException(SynchronizationConfigException::class);
        $extractor->getRelationsToSyncables($this->entityNegativeThird);
    }

    /**
     * @test
     * @dataProvider providePathForExplode
     */
    public function I_can_get_exploded_path(string $path, array $expectedResult): void
    {
        $extractor = $this->createExtractor();
        $result = $extractor->explode($path);

        $this->assertEquals($result, $expectedResult);
    }

    public function providePathForExplode(): array
    {
        return [
            'root' => ['.', ['.']],
            '2 parts' => ['a.b', ['a', 'b']],
            '3 parts' => ['a.b.c', ['a', 'b', 'c']],
        ];
    }

    /**
     * @test
     */
    public function I_can_check_operation_attribute_positive(): void
    {
        $operations = ['a.b' => ['code']];

        $attributeInstance = new AsSyncableEntity($operations);
        $this->assertEquals($attributeInstance->getOperations(), $operations);
    }

    /**
     * @test
     */
    public function I_can_check_operation_attribute_negative_codes(): void
    {
        $operations = ['a.b' => [123]];

        $this->expectException(SynchronizationConfigException::class);
        $attributeInstance = new AsSyncableEntity($operations);
    }

    /**
     * @test
     */
    public function I_can_check_operation_attribute_negative_path(): void
    {
        $operations = [0 => ['code']];

        $this->expectException(SynchronizationConfigException::class);
        $attributeInstance = new AsSyncableEntity($operations);
    }
}
