<?php

declare(strict_types=1);

namespace Tests\Gtt\SynchronizationPlugin\Unit;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use PHPUnit\Framework\TestCase;
use Gtt\SynchronizationPlugin\Api\Input\RawInputEnvelopInterface;
use Gtt\SynchronizationPlugin\DoctrineService\SynchronizationInboxService;
use Gtt\SynchronizationPlugin\DTO\Contract\TransferableInputInterface;
use Gtt\SynchronizationPlugin\DTO\Contract\TransferableItemInterface;
use Gtt\SynchronizationPlugin\Entity\Synchronization;
use Gtt\SynchronizationPlugin\Exception\SynchronizationCorruptedException;
use Sylius\Component\Resource\Factory\Factory;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Tests\Gtt\SynchronizationPlugin\Application\Synchronization\Processing\TestInputClient;

class SynchronizationInboxServiceTest extends TestCase
{
    private bool $hasExecutedQuery;

    protected function setUp(): void
    {
        $this->hasExecutedQuery = false;
    }

    private function createInboxService(?bool $isBadLastInsertId = false): SynchronizationInboxService
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

        return new SynchronizationInboxService($factory, $emMock);
    }

    private function dummyExecuteQuery(): Result
    {
        $this->hasExecutedQuery = true;

        return $this->createMock(Result::class);
    }

    /**
     * @test
     */
    public function I_test_insert_emergeny_synchronization_positive(): void
    {
        $this->assertFalse($this->hasExecutedQuery);

        $service = $this->createInboxService();
        $input = $this->createRawInputEnvelop();
        $client = new TestInputClient();

        $service->insertEmergencySynchronization($input, $client, 'test');

        $this->assertTrue($this->hasExecutedQuery);
    }

    /**
     * @test
     */
    public function I_test_insert_emergency_synchronization_negative(): void
    {
        $service = $this->createInboxService(true);
        $input = $this->createRawInputEnvelop();
        $client = new TestInputClient();

        $this->expectException(SynchronizationCorruptedException::class);
        $service->insertEmergencySynchronization($input, $client, 'test');
    }

    /**
     * @test
     */
    public function I_test_prepare_incoming_synchronizations_positive(): void
    {
        $service = $this->createInboxService();
        $input = $this->createTransferableInput();
        $client = new class extends TestInputClient
        {
            public function getPreparedSerializer(): Serializer
            {
                return new Serializer([new ArrayDenormalizer(), new ObjectNormalizer()], [new JsonEncoder()]);
            }
        };

        $result = $service->prepareIncomingSynchronizations($input, $client);

        $this->assertTrue($this->hasExecutedQuery);
        $this->assertEquals(1, count($result));

        foreach ($result as $synchronization) {
            $this->assertEquals($synchronization->getOperationCode(), $client->getOperationCode());
            $this->assertEquals($synchronization->getType(), $client->getType());
        }
    }

    private function createRawInputEnvelop(
        string $operationId = 'test-id',
        string $operationCode = 'test',
        array $data = [],
    ): RawInputEnvelopInterface
    {
        return new class($operationId, $operationCode, $data) implements RawInputEnvelopInterface
        {
            public function __construct(
                readonly string $operationId,
                readonly string $operationCode,
                readonly array $data
            )
            {
            }

            public function getData(): array
            {
                return $this->data;
            }

            public function getOperationId(): string
            {
                return $this->operationId;
            }

            public function getOperationCode(): string
            {
                return $this->operationCode;
            }

        };
    }

    private function createTransferableInput(): TransferableInputInterface
    {
        return new class implements TransferableInputInterface
        {
            public function getOperationCode(): string
            {
                return 'test';
            }

            public function setOperationCode(string $operationCode): void
            {
            }

            public function getOperationId(): string
            {
                return 'test-id';
            }

            public function setOperationId(string $operationId): void
            {
            }

            public function getData(): array
            {
                return [$this->getTransferableItem()];
            }

            public function setData(array $data): void
            {
            }

            public function addToData(TransferableItemInterface $dto): void
            {
            }

            public function getFromData(string $id): ?TransferableItemInterface
            {
                return $this->getTransferableItem();
            }

            private function getTransferableItem(): TransferableItemInterface
            {
                return new class implements TransferableItemInterface
                {

                    public function getSyncId(): string
                    {
                        return 'test-sync-id';
                    }

                    public function setSyncId(string $syncId): void
                    {
                    }
                };
            }
        };
    }
}
