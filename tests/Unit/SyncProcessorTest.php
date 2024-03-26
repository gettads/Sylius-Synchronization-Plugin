<?php

declare(strict_types=1);

namespace Tests\Gtt\SynchronizationPlugin\Unit;

use PHPUnit\Framework\TestCase;
use Gtt\SynchronizationPlugin\Api\Input\ReceiveOperation;
use Gtt\SynchronizationPlugin\DoctrineEventListener\DTO\EntityChangeCollectionDto;
use Gtt\SynchronizationPlugin\DoctrineEventListener\DTO\EntityChangeDto;
use Gtt\SynchronizationPlugin\DoctrineService\SynchronizationAttributeDataExtractor;
use Gtt\SynchronizationPlugin\DoctrineService\SynchronizationInboxService;
use Gtt\SynchronizationPlugin\DoctrineService\SynchronizationOutboxService;
use Gtt\SynchronizationPlugin\DTO\Contract\TransferableInputInterface;
use Gtt\SynchronizationPlugin\DTO\Contract\TransferableItemInterface;
use Gtt\SynchronizationPlugin\DTO\Contract\TransferableOutputInterface;
use Gtt\SynchronizationPlugin\Entity\Synchronization;
use Gtt\SynchronizationPlugin\Entity\SynchronizationInterface;
use Gtt\SynchronizationPlugin\Exception\SynchronizationConfigException;
use Gtt\SynchronizationPlugin\Exception\SynchronizationInvalidInputException;
use Gtt\SynchronizationPlugin\Exception\SynchronizationInvalidOutputException;
use Gtt\SynchronizationPlugin\Processing\Client\SynchronizationInputClientInterface;
use Gtt\SynchronizationPlugin\Processing\Client\SynchronizationOutputClientInterface;
use Gtt\SynchronizationPlugin\Processing\SyncProcessor;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Tests\Gtt\SynchronizationPlugin\Application\Entity\TestSyncableEntityFirst;
use Tests\Gtt\SynchronizationPlugin\Application\Synchronization\Processing\TestInputClient;
use Tests\Gtt\SynchronizationPlugin\Application\Synchronization\Processing\TestOutputClient;

class SyncProcessorTest extends TestCase
{
    private const INCOMING = true;
    private const OUTCOMING = false;

    private function createSyncProcessor(bool $isIncoming, ?string $error = null): SyncProcessor
    {
        $route = $isIncoming ? SyncProcessor::RECEIVER_ROUTE : 'outcoming_flow';
        $request = new Request([], [], ['_route' => $route]);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $extractor = $this->createConfiguredMock(SynchronizationAttributeDataExtractor::class, [
            'checkEntitiesConfigurations' => true,
        ]);

        $outboxService = $this->createMock(SynchronizationOutboxService::class);
        $outboxService->method('updateStatus');
        $outboxService->method('deleteSynchronization');
        $outboxService->method('updateOutcomingSynchronizationByTransferable');

        $inboxService = $this->createMock(SynchronizationInboxService::class);
        $inboxService->method('insertEmergencySynchronization');

        if (is_string($error)) {
            $outboxService->method('prepareOutcomingSynchronizations')->willThrowException(new \Exception($error));
            $inboxService->method('prepareIncomingSynchronizations')->willThrowException(new \Exception($error));
        } else {
            $syncEntity = new Synchronization();
            $syncEntity->setFlowType(
                $isIncoming
                    ? SynchronizationInterface::FLOW_TYPE_INCOMING
                    : SynchronizationInterface::FLOW_TYPE_OUTCOMING
            );
            $syncEntity->setStatus(SynchronizationInterface::STATUS_SYNCHRONIZATION);
            $syncEntity->setOperationCode('test-code');
            $syncEntity->setType('test');
            $syncEntity->setOperationId('test');
            $syncEntity->setPayload([]);
            $syncEntity->setSyncId('test-sync-id');

            $outboxService->method('prepareOutcomingSynchronizations')->willReturn([$syncEntity]);
            $inboxService->method('prepareIncomingSynchronizations')->willReturn([$syncEntity]);
        }

        $logger = $this->createMock(LoggerInterface::class);

        return new SyncProcessor($requestStack, $extractor, $outboxService, $inboxService, $logger);
    }

    /**
     * @test
     */
    public function I_add_input_client_positive(): void
    {
        $processor = $this->createSyncProcessor(self::INCOMING);

        $client = $this->createConfiguredMock(SynchronizationInputClientInterface::class, [
            'getOperationCode' => 'test',
            'getType' => 'test',
            'getCrudTypes' => ['test'],
        ]);

        $processor->addInputClient($client);

        $this->assertEquals(1, count($processor->getInputClients()), 'No exceptions');
    }

    /**
     * @test
     */
    public function I_add_input_client_negative_dublicate(): void
    {
        $processor = $this->createSyncProcessor(self::INCOMING);

        $client = $this->createConfiguredMock(SynchronizationInputClientInterface::class, [
            'getOperationCode' => 'test',
            'getType' => 'test',
            'getCrudTypes' => ['test'],
        ]);

        $processor->addInputClient($client);

        $this->assertEquals(1, count($processor->getInputClients()), 'No exceptions');

        $this->expectException(SynchronizationConfigException::class);
        $processor->addInputClient($client);
    }

    /**
     * @test
     */
    public function I_add_output_client_positive(): void
    {
        $processor = $this->createSyncProcessor(self::OUTCOMING);

        $client = $this->createConfiguredMock(SynchronizationOutputClientInterface::class, [
            'getOperationCode' => 'test',
            'getType' => 'test',
            'getCrudTypes' => ['test'],
        ]);

        $processor->addOutputClient($client);

        $this->assertEquals(1, count($processor->getOutputClients()), 'No exceptions');
    }

    /**
     * @test
     */
    public function I_add_output_client_negative_dublicate(): void
    {
        $processor = $this->createSyncProcessor(self::OUTCOMING);

        $client = $this->createConfiguredMock(SynchronizationOutputClientInterface::class, [
            'getOperationCode' => 'test',
            'getType' => 'test',
            'getCrudTypes' => ['test'],
        ]);

        $processor->addOutputClient($client);

        $this->assertEquals(1, count($processor->getOutputClients()), 'No exceptions');

        $this->expectException(SynchronizationConfigException::class);
        $processor->addOutputClient($client);
    }

    /**
     * @test
     */
    public function I_validate_client_negative_by_empty_properties(): void
    {
        $processor = $this->createSyncProcessor(self::OUTCOMING);

        $client = $this->createConfiguredMock(SynchronizationOutputClientInterface::class, [
            'getOperationCode' => '',
            'getType' => 'test',
            'getCrudTypes' => ['test'],
        ]);

        try {
            $processor->addOutputClient($client);
        } catch (SynchronizationConfigException $exception) {
            $this->assertEquals(
                $exception->getMessage(),
                'Synchronization config is invalid. Constant CODE is not set in ' . $client::class,
            );
        }


        $client = $this->createConfiguredMock(SynchronizationOutputClientInterface::class, [
            'getOperationCode' => 'test',
            'getType' => '',
            'getCrudTypes' => ['test'],
        ]);

        try {
            $processor->addOutputClient($client);
        } catch (SynchronizationConfigException $exception) {
            $this->assertEquals(
                $exception->getMessage(),
                'Synchronization config is invalid. Constant TYPE is not set in ' . $client::class,
            );
        }

        $client = $this->createConfiguredMock(SynchronizationOutputClientInterface::class, [
            'getOperationCode' => 'test',
            'getType' => 'test',
            'getCrudTypes' => [],
        ]);

        try {
            $processor->addOutputClient($client);
        } catch (SynchronizationConfigException $exception) {
            $this->assertEquals(
                $exception->getMessage(),
                'Synchronization config is invalid. Constant CRUD_TYPES is not set in ' . $client::class,
            );
        }
    }

    /**
     * @test
     */
    public function I_check_is_incoming_operation(): void
    {
        $processor = $this->createSyncProcessor(self::INCOMING);
        $this->assertTrue($processor->isIncomingOperation());

        $processor = $this->createSyncProcessor(self::OUTCOMING);
        $this->assertFalse($processor->isIncomingOperation());
    }

    /**
     * @test
     */
    public function I_check_entities_by_clients(): void
    {
        $processor = $this->createSyncProcessor(self::OUTCOMING);
        $client = new TestOutputClient();
        $processor->addOutputClient($client);

        $this->assertTrue($processor->checkEntitiesByClients());
    }

    /**
     * @test
     */
    public function I_check_synchronize_incoming(): void
    {
        $processor = $this->createSyncProcessor(self::INCOMING);
        $client = new TestInputClient();
        $processor->addInputClient($client);
        $processor->addInputClient(new class extends TestInputClient {
            public const CODE = '00_TEST_CLIENT_CODE';
            public function isSupported(ReceiveOperation $receiveOperation): bool
            {
                return false;
            }
        });

        $data = [new class implements TransferableItemInterface {
            public function getSyncId(): string
            {
                return 'test-sync-id';
            }

            public function setSyncId(string $syncId): void {}
        }];
        $operation = new ReceiveOperation('test-id', TestInputClient::CODE, $data);
        $errors = $processor->synchronizeIncoming($operation);

        $this->assertEquals([], $errors);
    }

    /**
     * @test
     */
    public function I_check_synchronize_incoming_negative_bad_flow(): void
    {
        $processor = $this->createSyncProcessor(self::OUTCOMING);
        $client = new TestInputClient();
        $processor->addInputClient($client);

        $data = [new class implements TransferableItemInterface {
            public function getSyncId(): string
            {
                return 'test-sync-id';
            }

            public function setSyncId(string $syncId): void {}
        }];
        $operation = new ReceiveOperation('test-id', TestInputClient::CODE, $data);

        $this->expectException(SynchronizationInvalidInputException::class);
        $processor->synchronizeIncoming($operation);
    }

    /**
     * @test
     */
    public function I_check_synchronize_incoming_negative_by_serializer_exception(): void
    {
        $processor = $this->createSyncProcessor(self::INCOMING);
        $client = new class extends TestInputClient {
            public function buildTransferableInput(ReceiveOperation $receiveOperation): ?TransferableInputInterface
            {
                throw new \Exception('Building of transferable object is corrupted.', 400);
            }
        };
        $processor->addInputClient($client);

        $operation = new ReceiveOperation('test-id', TestInputClient::CODE, []);

        $errors = $processor->synchronizeIncoming($operation);

        $this->assertEquals(1, count($errors));
    }

    /**
     * @test
     */
    public function I_check_synchronize_incoming_negative_by_inbox_service_exception(): void
    {
        $error = 'Error inside box service';
        $processor = $this->createSyncProcessor(self::INCOMING, $error);
        $client = new TestInputClient();
        $processor->addInputClient($client);

        $operation = new ReceiveOperation('test-id', TestInputClient::CODE, []);

        $errors = $processor->synchronizeIncoming($operation);

        $this->assertEquals(1, count($errors));
        $this->assertEquals($error, current($errors[0]));
    }

    /**
     * @test
     */
    public function I_check_synchronize_incoming_negative_by_logic_error(): void
    {
        $processor = $this->createSyncProcessor(self::INCOMING);
        $client = new class extends TestInputClient {
            public function buildTransferableInput(ReceiveOperation $receiveOperation): ?TransferableInputInterface
            {
                return new class implements TransferableInputInterface {
                    public function getOperationCode(): string
                    {
                        return 'test';
                    }

                    public function setOperationCode(string $operationCode): void{}

                    public function getOperationId(): string
                    {
                        return 'test-id';
                    }

                    public function setOperationId(string $operationId): void {}

                    public function getData(): array
                    {
                        return [$this->getTransferableItem()];
                    }

                    public function setData(array $data): void {}

                    public function addToData(TransferableItemInterface $dto): void {}

                    public function getFromData(string $id): ?TransferableItemInterface
                    {
                        return $this->getTransferableItem();
                    }

                    private function getTransferableItem(): TransferableItemInterface
                    {
                        return new class implements TransferableItemInterface {

                            public function getSyncId(): string
                            {
                                return 'incorrect';
                            }

                            public function setSyncId(string $syncId): void {}
                        };
                    }
                };
            }
        };
        $processor->addInputClient($client);

        $operation = new ReceiveOperation('test-id', TestInputClient::CODE, []);

        $errors = $processor->synchronizeIncoming($operation);

        $this->assertEquals(1, count($errors));
        $this->assertNotEmpty($errors[Response::HTTP_INTERNAL_SERVER_ERROR][\LogicException::class]);
    }

    /**
     * @test
     */
    public function I_check_synchronize_incoming_negative_by_transport_exception(): void
    {
        $processor = $this->createSyncProcessor(self::INCOMING);
        $client = new class extends TestInputClient {
            public function synchronizeInput(
                SynchronizationInterface $synchronization,
                TransferableItemInterface $item)
            : void {
                throw new \Exception('Transport error.', 500);
            }
        };
        $processor->addInputClient($client);

        $operation = new ReceiveOperation('test-id', TestInputClient::CODE, []);

        $errors = $processor->synchronizeIncoming($operation);

        $this->assertEquals(1, count($errors));
    }

    /**
     * @test
     */
    public function I_check_synchronize_outcoming(): void
    {
        $processor = $this->createSyncProcessor(self::OUTCOMING);
        $client = new TestOutputClient();
        $processor->addOutputClient($client);
        $processor->addOutputClient(new class extends TestOutputClient {
            public const CODE = '00_TEST_CLIENT_CODE';
            public function isSupported(EntityChangeCollectionDto $dto): bool
            {
                return false;
            }
        });

        $appliedChanges = new EntityChangeCollectionDto(true);
        $appliedChanges->add(new EntityChangeDto(
            new TestSyncableEntityFirst(),
            ['id' => [1, 111]],
            EntityChangeDto::CRUD_TYPE_UPDATE,
        ));

        $errors = $processor->synchronizeOutcoming($appliedChanges, $appliedChanges);

        $this->assertEquals([], $errors);
    }

    /**
     * @test
     */
    public function I_check_synchronize_outcoming_negative_bad_flow(): void
    {
        $processor = $this->createSyncProcessor(self::INCOMING);
        $client = new TestOutputClient();
        $processor->addOutputClient($client);

        $appliedChanges = new EntityChangeCollectionDto(true);
        $appliedChanges->add(new EntityChangeDto(
            new TestSyncableEntityFirst(),
            ['id' => [1, 111]],
            EntityChangeDto::CRUD_TYPE_UPDATE,
        ));

        $errors = $processor->synchronizeOutcoming($appliedChanges, $appliedChanges);

        $this->assertEquals(1, count($errors));
        $this->assertEquals('Try of outcoming synchronization on incoming endpoint is prevented.', reset($errors));
    }

    /**
     * @test
     */
    public function I_check_synchronize_incoming_negative_by_outbox_service_exception(): void
    {
        $error = 'Error inside box service';
        $processor = $this->createSyncProcessor(self::OUTCOMING, $error);
        $client = new class extends TestOutputClient {
            public function buildTransferableOutput(
                EntityChangeCollectionDto $appliedOnlyChanges,
                EntityChangeCollectionDto $allChronologyChanges,
                SynchronizationInterface $synchronization,
            ): ?TransferableOutputInterface
            {
                return null;
            }
        };
        $processor->addOutputClient($client);

        $appliedChanges = new EntityChangeCollectionDto(true);
        $appliedChanges->add(new EntityChangeDto(
            new TestSyncableEntityFirst(),
            ['id' => [1, 111]],
            EntityChangeDto::CRUD_TYPE_UPDATE,
        ));

        $errors = $processor->synchronizeOutcoming($appliedChanges, $appliedChanges);

        $this->assertEquals(1, count($errors));
        $this->assertEquals($error, current($errors));
    }

    /**
     * @test
     */
    public function I_check_synchronize_outcoming_negative_by_mapping_exception(): void
    {
        $processor = $this->createSyncProcessor(self::OUTCOMING);
        $client = new class extends TestOutputClient {
            public function buildTransferableOutput(
                EntityChangeCollectionDto $appliedOnlyChanges,
                EntityChangeCollectionDto $allChronologyChanges,
                SynchronizationInterface $synchronization,
            ): ?TransferableOutputInterface
            {
                throw new SynchronizationInvalidOutputException('Build transferable error.');
            }
        };
        $processor->addOutputClient($client);

        $appliedChanges = new EntityChangeCollectionDto(true);
        $appliedChanges->add(new EntityChangeDto(
            new TestSyncableEntityFirst(),
            ['id' => [1, 111]],
            EntityChangeDto::CRUD_TYPE_UPDATE,
        ));

        $errors = $processor->synchronizeOutcoming($appliedChanges, $appliedChanges);

        $this->assertEquals(1, count($errors));
    }

    /**
     * @test
     */
    public function I_check_synchronize_outcoming_negative_by_transport_exception(): void
    {
        $processor = $this->createSyncProcessor(self::OUTCOMING);
        $client = new class extends TestOutputClient {
            public function synchronizeOutput(TransferableOutputInterface $dto): void
            {
                throw new \Exception('Transport error.');
            }
        };
        $processor->addOutputClient($client);

        $appliedChanges = new EntityChangeCollectionDto(true);
        $appliedChanges->add(new EntityChangeDto(
            new TestSyncableEntityFirst(),
            ['id' => [1, 111]],
            EntityChangeDto::CRUD_TYPE_UPDATE,
        ));

        $errors = $processor->synchronizeOutcoming($appliedChanges, $appliedChanges);

        $this->assertEquals(1, count($errors));
    }
}
