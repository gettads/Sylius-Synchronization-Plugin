<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\Api\Handler;

use Doctrine\ORM\EntityManagerInterface;
use Gtt\SynchronizationPlugin\Api\Input\SyncStatusOperation;
use Gtt\SynchronizationPlugin\Api\Validation\ConstraintSyncStatusOperation;
use Gtt\SynchronizationPlugin\DoctrineService\SynchronizationOutboxService;
use Gtt\SynchronizationPlugin\Entity\SynchronizationInterface;
use Gtt\SynchronizationPlugin\Exception\SynchronizationInvalidInputException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Throwable;

#[AsMessageHandler]
final class SyncStatusOperationHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SynchronizationOutboxService $service,
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * @return array<string|\Stringable>
     */
    private function getValidationErrors(SyncStatusOperation $statusOperation): array
    {
        $errors = [];

        try {
            $violations = $this->validator->validate($statusOperation, new ConstraintSyncStatusOperation());

            foreach ($violations as $violation) {
                assert($violation instanceof ConstraintViolation);
                $errors[] = $violation->getMessage();
            }
        } catch (Throwable $throwable) {
            $errors[] = $throwable->getMessage();
        }

        return $errors;
    }

    public function __invoke(SyncStatusOperation $statusOperation): void
    {
        $errors = $this->getValidationErrors($statusOperation);

        if ($errors !== []) {
            throw new SynchronizationInvalidInputException(implode('; ', $errors));
        }

        $synchronization = $this->entityManager->getRepository(SynchronizationInterface::class)->findOneBy(
            [
                'operationId' => $statusOperation->getOperationId(),
                'syncId' => $statusOperation->getSyncId(),
            ],
        );

        if ($synchronization === null) {
            throw new NotFoundHttpException(
                sprintf(
                    'Synchronization not found by operationId: "%s" and syncId: "%s"',
                    $statusOperation->getOperationId(),
                    $statusOperation->getSyncId(),
                ),
            );
        }

        assert($synchronization instanceof SynchronizationInterface);

        $this->service->updateStatus(
            $synchronization,
            $statusOperation->getStatus(),
            $statusOperation->getErrorMessage(),
        );
    }
}
