<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\Api\Validation;

use Symfony\Component\Validator\Constraint;

class ConstraintReceiveOperation extends Constraint
{
    public string $messageNotSet = 'Request key "{{ key }}" has empty value.';

    public string $messageItemSyncId = 'Request key "data.[{{ index }}].syncId" is empty.';

    public function validatedBy(): string
    {
        return ConstraintReceiveOperationValidator::class;
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
