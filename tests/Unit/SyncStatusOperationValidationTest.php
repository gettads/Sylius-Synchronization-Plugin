<?php

declare(strict_types=1);

namespace Tests\Gtt\SynchronizationPlugin\Unit;

use Gtt\SynchronizationPlugin\Api\Input\SyncStatusOperation;
use Gtt\SynchronizationPlugin\Api\Validation\ConstraintSyncStatusOperation;
use Gtt\SynchronizationPlugin\Api\Validation\ConstraintSyncStatusOperationValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;


class SyncStatusOperationValidationTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ConstraintSyncStatusOperationValidator
    {
        return new ConstraintSyncStatusOperationValidator();
    }

    /**
     * @test
     */
    public function validate_positive(): void
    {
        $constraint = new ConstraintSyncStatusOperation();
        $this->assertTrue($constraint->getTargets() === ConstraintSyncStatusOperation::CLASS_CONSTRAINT);
        $this->assertTrue($constraint->validatedBy() === ConstraintSyncStatusOperationValidator::class);

        $input = new SyncStatusOperation('operation_id');
        $input->setStatus('sync_ok');
        $input->setSyncId('sync_id');
        $input->setOperationId('operation_id');

        $this->validator->validate($input, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @test
     */
    public function validate_negative_data(): void
    {
        $input = new SyncStatusOperation();
        $violations = $this->validator->validate($input, new ConstraintSyncStatusOperation());
        assert($violations instanceof ConstraintViolationList);
        $this->assertEquals($violations->count(), 3, '3 required fields are empty.');

        $input->setStatus('sync_ok');
        $input->setSyncId('sync_id');
        $input->setOperationId('operation_id');
        $input->setStatus('__unknown');
        $violations = $this->validator->validate($input, new ConstraintSyncStatusOperation());
        assert($violations instanceof ConstraintViolationList);
        $this->assertEquals($violations->count(), 4, 'Previous 3 + unknown status');
    }

    /**
     * @test
     */
    public function validate_negative_value_type(): void
    {
        $this->expectException(UnexpectedTypeException::class);
        $this->validator->validate(new class {}, new ConstraintSyncStatusOperation());
    }

    /**
     * @test
     */
    public function validate_negative_constraint_type(): void
    {
        $input = new SyncStatusOperation('operation_id');
        $input->setStatus('sync_ok');
        $input->setSyncId('sync_id');
        $input->setOperationId('operation_id');
        $input->setErrorMessage(null);

        $this->expectException(UnexpectedTypeException::class);
        $this->validator->validate($input, new class extends Constraint {});
    }

}
