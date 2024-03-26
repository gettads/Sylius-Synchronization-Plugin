<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\Api\Validation;

use Gtt\SynchronizationPlugin\Api\Input\ReceiveOperation;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ConstraintReceiveOperationValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): ConstraintViolationListInterface
    {
        if (!$constraint instanceof ConstraintReceiveOperation) {
            throw new UnexpectedTypeException($constraint, ConstraintReceiveOperation::class);
        }

        if (!$value instanceof ReceiveOperation) {
            throw new UnexpectedTypeException($value, ReceiveOperation::class);
        }

        $this->sanitize($value);

        foreach (ReceiveOperation::PROPERTIES as $property) {
            $propertyValue = match ($property) {
                ReceiveOperation::PROPERTY_OPERATION_ID => $value->getOperationId(),
                ReceiveOperation::PROPERTY_OPERATION_CODE => $value->getOperationCode(),
                ReceiveOperation::PROPERTY_DATA => $value->getData(),
            };

            if (
                ($property === ReceiveOperation::PROPERTY_DATA && $propertyValue === [])
                || $propertyValue === ''
            ) {
                $this->context->buildViolation($constraint->messageNotSet)
                    ->setParameter('{{ key }}', $property)
                    ->addViolation();
            }
        }

        foreach ($value->getData() as $index => $item) {
            if (!isset($item['syncId']) || trim($item['syncId']) === '') {
                $this->context->buildViolation($constraint->messageItemSyncId)
                    ->setParameter('{{ index }}', (string) $index)
                    ->addViolation();
            }
        }

        return $this->context->getViolations();
    }

    private function sanitize(ReceiveOperation $value): void
    {
        $value->setOperationId(trim((string) $value->getOperationId()));
        $value->setOperationCode(trim((string) $value->getOperationCode()));
    }
}
