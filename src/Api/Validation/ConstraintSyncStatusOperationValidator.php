<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\Api\Validation;

use Gtt\SynchronizationPlugin\Api\Input\SyncStatusOperation;
use Gtt\SynchronizationPlugin\Entity\SynchronizationInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ConstraintSyncStatusOperationValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): ConstraintViolationListInterface
    {
        if (!$constraint instanceof ConstraintSyncStatusOperation) {
            throw new UnexpectedTypeException($constraint, ConstraintSyncStatusOperation::class);
        }

        if (!$value instanceof SyncStatusOperation) {
            throw new UnexpectedTypeException($value, SyncStatusOperation::class);
        }

        $this->sanitize($value);

        $isStatusErrorAdded = false;

        foreach (['operationId', 'status', 'syncId'] as $property) {
            $accessor = 'get' . ucfirst($property);

            if ($value->{$accessor}() === '') {
                $isStatusErrorAdded = !$isStatusErrorAdded && $property === 'status' ? true : $isStatusErrorAdded;
                $this->context->buildViolation($constraint->messageNotSet)
                    ->setParameter('{{ key }}', $property)
                    ->addViolation();
            }
        }

        if (
            !$isStatusErrorAdded
            && !in_array($value->getStatus(), SynchronizationInterface::STATUSES, true)
        ) {
            $this->context->buildViolation($constraint->messageUnknownStatus)
                ->setParameter('{{ status }}', $value->getStatus())
                ->addViolation();
        }

        return $this->context->getViolations();
    }

    private function sanitize(SyncStatusOperation $value): void
    {
        $value->setOperationId(trim((string)$value->getOperationId()));
        $value->setStatus(trim((string)$value->getStatus()));
        $value->setSyncId(trim((string)$value->getSyncId()));
    }
}
