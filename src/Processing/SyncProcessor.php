<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\Processing;

use LogicException;
use Gtt\SynchronizationPlugin\Api\Input\ReceiveOperation;
use Gtt\SynchronizationPlugin\DoctrineEventListener\DTO\EntityChangeCollectionDto;
use Gtt\SynchronizationPlugin\DoctrineService\SynchronizationAttributeDataExtractor;
use Gtt\SynchronizationPlugin\DoctrineService\SynchronizationInboxService;
use Gtt\SynchronizationPlugin\DoctrineService\SynchronizationOutboxService;
use Gtt\SynchronizationPlugin\DTO\Contract\TransferableItemInterface;
use Gtt\SynchronizationPlugin\Entity\SynchronizationInterface;
use Gtt\SynchronizationPlugin\Exception\SynchronizationConfigException;
use Gtt\SynchronizationPlugin\Exception\SynchronizationInvalidInputException;
use Gtt\SynchronizationPlugin\Exception\SynchronizationInvalidOutputException;
use Gtt\SynchronizationPlugin\Processing\Client\SynchronizationClientInterface;
use Gtt\SynchronizationPlugin\Processing\Client\SynchronizationInputClientInterface;
use Gtt\SynchronizationPlugin\Processing\Client\SynchronizationOutputClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SyncProcessor
{
    public const CLIENTS_OUTPUT_SERVICE_TAG = 'app.sync.processor.client.output';

    public const CLIENTS_INTPUT_SERVICE_TAG = 'app.sync.processor.client.input';

    public const ADD_INPUT_CLIENT_METHOD = 'addInputClient';

    public const ADD_OUTPUT_CLIENT_METHOD = 'addOutputClient';

    public const CHECK_ENTITIES_BY_CLIENTS_METHOD = 'checkEntitiesByClients';

    public const RECEIVER_ROUTE = 'api_synchronizations_sync_receive_collection';

    /**
     * @var array<string, SynchronizationInputClientInterface>
     */
    private array $inputClients = [];

    /**
     * @var array<string, SynchronizationOutputClientInterface>
     */
    private array $outputClients = [];

    public function __construct(
        private RequestStack $requestStack,
        private SynchronizationAttributeDataExtractor $extractor,
        private SynchronizationOutboxService $outboxService,
        private SynchronizationInboxService $inboxService,
        private LoggerInterface $logger,
    ) {
    }

    public function addInputClient(SynchronizationInputClientInterface $client): void
    {
        $this->validateClient($client);

        $code = $client->getOperationCode();

        if (isset($this->inputClients[$code])) {
            throw new SynchronizationConfigException('Code ' . $code . ' has already set in ' . $client::class);
        }

        $this->inputClients[$code] = $client;
    }

    public function addOutputClient(SynchronizationOutputClientInterface $client): void
    {
        $this->validateClient($client);

        $code = $client->getOperationCode();

        if (isset($this->outputClients[$code])) {
            throw new SynchronizationConfigException('Code ' . $code . ' has already set in ' . $client::class);
        }

        $this->outputClients[$code] = $client;
    }

    /**
     * @return array<string, SynchronizationInputClientInterface>
     */
    public function getInputClients(): array
    {
        return $this->inputClients;
    }

    /**
     * @return array<string, SynchronizationOutputClientInterface>
     */
    public function getOutputClients(): array
    {
        return $this->outputClients;
    }

    public function checkEntitiesByClients(): ?bool
    {
        $operations = [];

        foreach ([...$this->inputClients, ...$this->outputClients] as $client) {
            assert($client instanceof SynchronizationClientInterface);
            $operations[$client->getOperationCode()] = $client->getOperationCode();
        }

        return $this->extractor->checkEntitiesConfigurations($operations);
    }

    /**
     * @return array<string|int, array<string>> Errors
     *
     * @throws SynchronizationInvalidInputException
     */
    public function synchronizeIncoming(ReceiveOperation $receiveOperation): array
    {
        $errors = [];

        if (!$this->isIncomingOperation()) {
            $message = 'Try of incoming synchronization on outcoming flow is prevented.';
            $this->logger->log(LogLevel::ERROR, $message);

            throw new SynchronizationInvalidInputException($message);
        }

        foreach ($this->getInputClients() as $client) {
            if (!$client->isSupported($receiveOperation)) {
                continue;
            }

            $transferableInput = null;
            /**
             * @var array<string, SynchronizationInterface> $synchronizations
             */
            $synchronizations = [];
            /**
             * @var array<string, TransferableItemInterface> $transferables
             */
            $transferables = [];

            try {
                $transferableInput = $client->buildTransferableInput($receiveOperation);

                foreach ($transferableInput->getData() as $transferableItem) {
                    $transferables[$transferableItem->getSyncId()] = $transferableItem;
                }
            } catch (Throwable $exception) {
                $errors[$exception->getCode()][$exception::class] = $exception->getMessage();
                $message = sprintf('%s Stacktrace: %s', $exception->getMessage(), $exception->getTraceAsString());
                $this->inboxService->insertEmergencySynchronization($receiveOperation, $client, $message);
                $this->logger->log(LogLevel::ERROR, $message);

                continue;
            }

            try {
                $synchronizations = $this->inboxService->prepareIncomingSynchronizations($transferableInput, $client);
            } catch (Throwable $exception) {
                $message = sprintf('%s Stacktrace: %s', $exception->getMessage(), $exception->getTraceAsString());
                $this->inboxService->insertEmergencySynchronization($receiveOperation, $client, $message);
                $this->logger->log(LogLevel::ERROR, $message);
                $errors[$exception->getCode()][$exception::class] = $exception->getMessage();

                continue;
            }

            foreach ($synchronizations as $synchronization) {
                if (!isset($transferables[$synchronization->getSyncId()])) {
                    $message = sprintf(
                        'Transferable object was not created for operationId: %s, syncId: %s',
                        $synchronization->getOperationId(),
                        $synchronization->getSyncId(),
                    );
                    $this->inboxService->insertEmergencySynchronization($receiveOperation, $client, $message);
                    $this->logger->log(LogLevel::ERROR, $message);
                    $errors[Response::HTTP_INTERNAL_SERVER_ERROR][LogicException::class] = $message;

                    continue;
                }

                try {
                    $client->synchronizeInput($synchronization, $transferables[$synchronization->getSyncId()]);
                    $this->outboxService->updateStatus(
                        $synchronization,
                        SynchronizationInterface::STATUS_SUCCESS_SYNC,
                    );
                } catch (Throwable $exception) {
                    $errors[$exception->getCode()][$exception::class] = $exception->getMessage();
                    $text = sprintf('%s Stacktrace: %s', $exception->getMessage(), $exception->getTraceAsString());
                    $this->logger->log(LogLevel::ERROR, $text);
                    $this->outboxService->updateStatus(
                        $synchronization,
                        SynchronizationInterface::STATUS_ERROR_ON_SYNC_TRANSPORT,
                        $text,
                    );
                }
            }
        }

        return $errors;
    }

    /**
     * @return array<string>
     */
    public function synchronizeOutcoming(
        EntityChangeCollectionDto $appliedChanges,
        EntityChangeCollectionDto $allChronologyChanges,
    ): array {
        if ($this->isIncomingOperation()) {
            $message = 'Try of outcoming synchronization on incoming endpoint is prevented.';
            $this->logger->log(LogLevel::ERROR, $message);

            return [$message];
        }

        $errors = [];

        foreach ($this->getOutputClients() as $client) {
            if (!$client->isSupported($appliedChanges)) {
                continue;
            }

            /**
             * @var array<int, SynchronizationInterface> $synchronizations
             */
            $synchronizations = [];

            try {
                $synchronizations = $this->outboxService->prepareOutcomingSynchronizations($appliedChanges, $client);
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();
                $this->logger->log(
                    LogLevel::ERROR,
                    sprintf('%s Stacktrace: %s', $exception->getMessage(), $exception->getTraceAsString()),
                );
            }

            if ($synchronizations === []) {
                continue;
            }

            foreach ($synchronizations as $synchronization) {
                try {
                    $out = $client->buildTransferableOutput($appliedChanges, $allChronologyChanges, $synchronization);

                    if ($out === null) {
                        $this->outboxService->deleteSynchronization($synchronization);

                        continue;
                    }

                    $this->outboxService->updateOutcomingSynchronizationByTransferable(
                        $client,
                        $synchronization,
                        $out
                    );
                    $client->synchronizeOutput($out);
                } catch (SynchronizationInvalidOutputException $exception) {
                    $errors[] = $exception->getMessage();
                    $text = sprintf('%s Stacktrace: %s', $exception->getMessage(), $exception->getTraceAsString());
                    $this->logger->log(LogLevel::ERROR, $text);
                    $this->outboxService->updateStatus(
                        $synchronization,
                        SynchronizationInterface::STATUS_ERROR_ON_SYNC_MAPPING,
                        $text,
                    );
                } catch (Throwable $exception) {
                    $errors[] = $exception->getMessage();
                    $text = sprintf('%s Stacktrace: %s', $exception->getMessage(), $exception->getTraceAsString());
                    $this->logger->log(LogLevel::ERROR, $text);
                    $this->outboxService->updateStatus(
                        $synchronization,
                        SynchronizationInterface::STATUS_ERROR_ON_SYNC_TRANSPORT,
                        $text
                    );
                }
            }
        }

        return $errors;
    }

    public function isIncomingOperation(): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request !== null && $request->get('_route') === self::RECEIVER_ROUTE;
    }

    private function validateClient(SynchronizationClientInterface $client): void
    {
        if ($client->getOperationCode() === '') {
            throw new SynchronizationConfigException('Constant CODE is not set in ' . $client::class);
        }

        if ($client->getType() === '') {
            throw new SynchronizationConfigException('Constant TYPE is not set in ' . $client::class);
        }

        if ($client->getCrudTypes() === []) {
            throw new SynchronizationConfigException('Constant CRUD_TYPES is not set in ' . $client::class);
        }
    }
}
