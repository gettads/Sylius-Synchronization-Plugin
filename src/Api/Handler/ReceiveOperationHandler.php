<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\Api\Handler;

use Gtt\SynchronizationPlugin\Api\Input\ReceiveOperation;
use Gtt\SynchronizationPlugin\Api\Validation\ConstraintReceiveOperation;
use Gtt\SynchronizationPlugin\Exception\SynchronizationInvalidInputException;
use Gtt\SynchronizationPlugin\Processing\SyncProcessor;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Throwable;

#[AsMessageHandler]
final class ReceiveOperationHandler
{
    public const ERRORS_REQUEST_ATTRIBUTE = 'sync_errors';

    public function __construct(
        private SyncProcessor $processor,
        private RequestStack $requestStack,
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * @return array<int|string, array<string, string|\Stringable>>
     */
    private function getValidationErrors(ReceiveOperation $receiveOperation): array
    {
        $errors = [];

        try {
            $violations = $this->validator->validate($receiveOperation, new ConstraintReceiveOperation());

            foreach ($violations as $violation) {
                assert($violation instanceof ConstraintViolation);
                $errors[Response::HTTP_BAD_REQUEST][SynchronizationInvalidInputException::class]
                    = $violation->getMessage();
            }
        } catch (Throwable $throwable) {
            $errors[$throwable->getCode()][$throwable::class] = $throwable->getMessage();
        }

        return $errors;
    }

    public function __invoke(ReceiveOperation $receiveOperation): void
    {
        $errors = $this->getValidationErrors($receiveOperation);

        if ($errors === []) {
            $errors = $this->processor->synchronizeIncoming($receiveOperation);
        }

        if ($errors !== []) {
            $this->requestStack->getCurrentRequest()->attributes->set(self::ERRORS_REQUEST_ATTRIBUTE, $errors);
        }
    }
}
