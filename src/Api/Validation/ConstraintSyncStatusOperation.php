<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\Api\Validation;

use Symfony\Component\Validator\Constraint;

class ConstraintSyncStatusOperation extends Constraint
{
    public string $messageNotSet = 'Request key "{{ key }}" is not set.';

    public string $messageUnknownStatus = 'Status "{{ status }}" is invalid.';

    public function validatedBy(): string
    {
        return ConstraintSyncStatusOperationValidator::class;
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
