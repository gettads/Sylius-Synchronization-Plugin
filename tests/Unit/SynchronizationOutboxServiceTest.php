<?php

declare(strict_types=1);

namespace Tests\Gtt\SynchronizationPlugin\Unit;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use PHPUnit\Framework\TestCase;
use Gtt\SynchronizationPlugin\DoctrineEventListener\DTO\EntityChangeCollectionDto;
use Gtt\SynchronizationPlugin\DoctrineEventListener\DTO\EntityChangeDto;
use Gtt\SynchronizationPlugin\DoctrineService\SynchronizationAttributeDataExtractor;
use Gtt\SynchronizationPlugin\DoctrineService\SynchronizationOutboxService;
use Gtt\SynchronizationPlugin\DTO\Contract\TransferableEnvelopTrait;
use Gtt\SynchronizationPlugin\DTO\Contract\TransferableOutputInterface;
use Gtt\SynchronizationPlugin\Entity\Synchronization;
use Gtt\SynchronizationPlugin\Entity\SynchronizationInterface;
use Gtt\SynchronizationPlugin\Exception\SynchronizationConfigException;
use Gtt\SynchronizationPlugin\Exception\SynchronizationCorruptedException;
use Sylius\Component\Resource\Factory\Factory;
use Sylius\Component\Resource\Model\ResourceInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Tests\Gtt\SynchronizationPlugin\Application\Entity\TestSyncableEntityFirst;
use Tests\Gtt\SynchronizationPlugin\Application\Entity\TestSyncableEntityNegativeThird;
use Tests\Gtt\SynchronizationPlugin\Application\Entity\TestSyncableEntitySecond;
use Tests\Gtt\SynchronizationPlugin\Application\Synchronization\Processing\TestOutputClient;

class SynchronizationOutboxServiceTest extends TestCase
{
    private bool $hasExecutedQuery;

    protected function setUp(): void
    {
        $this->hasExecutedQuery = false;
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

        return $this->createConfiguredMock(SynchronizationAttributeDataExtractor::class, $params);
    }

    private function createOutboxService(
        ?bool $isBadLastInsertId = false,
        ?array $extractorParams = null
    ): SynchronizationOutboxService
    {
        $factory = new Factory(Synchronization::class);

        $metadataInfoMock = $this->createConfiguredMock(ClassMetadataInfo::class, [
            'getTableName' => uniqid('table_name_'),
        ]);

        $statementMock = $this->createConfiguredMock(Statement::class, [
            'executeQuery' => $this->dummyExecuteQuery(),
        ]);

        $connectionMock = $this->createConfiguredMock(Connection::class, [
            'prepare' => $statementMock,
            'lastInsertId' => $isBadLastInsertId ? false : 1,
        ]);

        $emMock = $this->createConfiguredMock(EntityManagerInterface::class, [
            'getClassMetadata' => $metadataInfoMock,
            'getConnection' => $connectionMock,
        ]);

        $securityMock = $this->createConfiguredMock(Security::class, [
            'getUser' => new class implements UserInterface {

                public function getRoles(): array
                {
                    return [];
                }

                public function eraseCredentials() {}

                public function getUserIdentifier(): string
                {
                    return 'test-user-identifier';
                }
            },
        ]);

        $request = new Request([], [], ['_route' => 'test-outcoming-route']);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new SynchronizationOutboxService(
            $factory,
            $this->createExtractor($extractorParams),
            $securityMock,
            $requestStack,
            $emMock,
        );
    }

    private function dummyExecuteQuery(): Result
    {
        $this->hasExecutedQuery = true;

        return $this->createMock(Result::class);
    }

    private function createChangesCollectionDto(
        ?bool              $isSingleEntityMode = false,
        ?ResourceInterface $entityFirst = null,
        ?ResourceInterface $entitySecond = null,
        ?bool $isDelete = false,
    ): EntityChangeCollectionDto
    {
        $dto = new EntityChangeCollectionDto(true);

        $first = $entityFirst === null ? new TestSyncableEntityFirst() : $entityFirst;
        $type = $isDelete ? EntityChangeDto::CRUD_TYPE_DELETE : null;
        $changeFirst = new EntityChangeDto(
            $first,
            $isDelete ? ['id' => [$first->getId(), null]] : ['id' => [$first->getId(), 111]],
            $type ?? ($first->getId() === null ? EntityChangeDto::CRUD_TYPE_CREATE : EntityChangeDto::CRUD_TYPE_UPDATE),
        );
        $dto->add($changeFirst);

        if (!$isSingleEntityMode) {
            $second = $entitySecond === null ? new TestSyncableEntitySecond() : $entitySecond;
            $second->setFirst($first);
            $first->setSeconds([$second]);
            $changeSecond = new EntityChangeDto(
                $second,
                $isDelete ? ['id' => [$second->getId(), null]] : ['id' => [$second->getId(), 111]],
                $type
                    ?? (
                        $second->getId() === null
                            ? EntityChangeDto::CRUD_TYPE_CREATE
                            : EntityChangeDto::CRUD_TYPE_UPDATE
                ),
            );
            $dto->add($changeSecond);
        }

        return $dto;
    }

    /**
     * @test
     */
    public function I_test_prepare_outcoming_synchronizations_positive(): void
    {
        $service = $this->createOutboxService();
        $client = new TestOutputClient();

        // Create
        $dto = $this->createChangesCollectionDto(true, new class extends TestSyncableEntityFirst {
            public function getId(): ?int
            {
                return null;
            }
        });

        foreach ($service->prepareOutcomingSynchronizations($dto, $client) as $synchronization) {
            $payload = $synchronization->getPayload();
            $this->assertNotEmpty($payload['data']['create']);
            $this->assertEquals($client->getType(), $synchronization->getType());
            $this->assertEquals($client->getOperationCode(), $synchronization->getOperationCode());
        }

        // Update
        $dto = $this->createChangesCollectionDto();
        foreach ($service->prepareOutcomingSynchronizations($dto, $client) as $synchronization) {
            $payload = $synchronization->getPayload();
            $this->assertNotEmpty($payload['data']['update']);
            $this->assertEquals($client->getType(), $synchronization->getType());
            $this->assertEquals($client->getOperationCode(), $synchronization->getOperationCode());
        }


        // Delete
        $dto = $this->createChangesCollectionDto(true, null, null, true);
        $result = current($service->prepareOutcomingSynchronizations($dto, $client));
        assert($result instanceof SynchronizationInterface);
        $payload = $result->getPayload();
        $this->assertEquals(
            TestSyncableEntityFirst::DEFAULT_ID,
            $payload['data']['delete'][TestSyncableEntityFirst::class][TestSyncableEntityFirst::DEFAULT_ID]['id']['old']
        );
        $this->assertNull(
            $payload['data']['delete'][TestSyncableEntityFirst::class][TestSyncableEntityFirst::DEFAULT_ID]['id']['new']
        );
    }

    /**
     * @test
     */
    public function I_test_prepare_outcoming_synchronizations_negative_randomize_sync_id(): void
    {
        $service = $this->createOutboxService();

        $entityFirst = new class extends TestSyncableEntityFirst {
          public function getSyncId(): ?string
          {
              return uniqid('test__');
          }
        };

        $entitySecond = new TestSyncableEntitySecond();
        $dto = $this->createChangesCollectionDto(false, $entityFirst, $entitySecond);
        $client = new TestOutputClient();

        $result = $service->prepareOutcomingSynchronizations($dto, $client);

        $this->assertEquals([], $result);
    }

    /**
     * @test
     */
    public function I_test_prepare_outcoming_synchronizations_negative_by_last_insert_id(): void
    {
        $service = $this->createOutboxService(true);
        $dto = $this->createChangesCollectionDto();
        $client = new TestOutputClient();

        $this->expectException(SynchronizationCorruptedException::class);
        $service->prepareOutcomingSynchronizations($dto, $client);
    }

    /**
     * @test
     */
    public function I_test_prepare_outcoming_synchronizations_negative_bad_syncable(): void
    {
        $service = $this->createOutboxService(
            true,
            ['getSyncableEntity' => null, 'extractOperationCodes' => [TestSyncableEntityNegativeThird::BAD_CODE]],
        );
        $dto = $this->createChangesCollectionDto(true, new TestSyncableEntityNegativeThird());

        $client = new TestOutputClient();
        $result = $service->prepareOutcomingSynchronizations($dto, $client);
        $this->assertEquals([], $result , '$result === [] is OK by SynchronizationOutboxService::isStop() reason.');

        $client = new class extends TestOutputClient {
            public const CODE = TestSyncableEntityNegativeThird::BAD_CODE;
        };
        $this->expectException(SynchronizationConfigException::class);
        $service->prepareOutcomingSynchronizations($dto, $client);
    }

    /**
     * @test
     */
    public function I_test_update_outcoming_synchronization_by_transferable_positive(): void
    {
        $service = $this->createOutboxService();
        $client = new class extends TestOutputClient {
            public function getPreparedSerializer(): Serializer
            {
                return new Serializer([new ArrayDenormalizer(), new ObjectNormalizer()], [new JsonEncoder()]);
            }
        };
        $syncEntity = new Synchronization();
        $syncEntity->setId(1);
        $syncEntity->setPayload(['test_data']);
        $transferable = new class implements TransferableOutputInterface {
            use TransferableEnvelopTrait;

            public function __construct()
            {
            }
        };
        $transferable->setOperationId('test-operation-id');
        $transferable->setOperationCode('test-operation-code');
        $transferable->setData([]);

        $service->updateOutcomingSynchronizationByTransferable($client, $syncEntity, $transferable);

        $this->assertEquals(true, $this->hasExecutedQuery);
    }

    /**
     * @test
     */
    public function I_test_update_outcoming_synchronization_by_transferable_negative_by_unsaved_sync_entity(): void
    {
        $service = $this->createOutboxService();
        $client = new TestOutputClient();
        $syncEntity = new Synchronization();
        $transferable = new class implements TransferableOutputInterface {
            use TransferableEnvelopTrait;

            public function __construct()
            {
            }
        };

        $this->expectException(SynchronizationCorruptedException::class);
        $service->updateOutcomingSynchronizationByTransferable($client, $syncEntity, $transferable);
    }

    /**
     * @test
     */
    public function I_test_delete_synchronization_positive(): void
    {
        $service = $this->createOutboxService();
        $syncEntity = new Synchronization();
        $service->deleteSynchronization($syncEntity);
        $this->assertTrue($this->hasExecutedQuery);
    }

    /**
     * @test
     */
    public function I_test_update_status(): void
    {
        $service = $this->createOutboxService();
        $syncEntity = new Synchronization();
        $service->updateStatus($syncEntity, 'sync_success');
        $this->assertTrue($this->hasExecutedQuery);
    }
}
