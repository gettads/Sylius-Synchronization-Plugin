<?php

declare(strict_types=1);

namespace Tests\Gtt\SynchronizationPlugin\Unit;

use Gtt\SynchronizationPlugin\Api\Input\ReceiveOperation;
use Gtt\SynchronizationPlugin\Api\Validation\ConstraintReceiveOperation;
use Gtt\SynchronizationPlugin\Api\Validation\ConstraintReceiveOperationValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class ReceiveOperationValidationTest extends ConstraintValidatorTestCase
{
    /**
     * @test
     */
    public function validate_positive(): void
    {
        $constraint = new ConstraintReceiveOperation();
        $this->assertTrue($constraint->getTargets() === Constraint::CLASS_CONSTRAINT);
        $this->assertTrue($constraint->validatedBy() === ConstraintReceiveOperationValidator::class);

        $data = [
            1 => ['syncId' => 'syncId_1'],
            2 => ['syncId' => 'syncId_2'],
        ];
        $envelop = new ReceiveOperation('operation_id', 'operation_code', $data);

        $this->validator->validate($envelop, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @test
     */
    public function validate_negative_data(): void
    {
        $envelop = new ReceiveOperation('operation_id', 'operation_code', []);
        $violations = $this->validator->validate($envelop, new ConstraintReceiveOperation());
        assert($violations instanceof ConstraintViolationList);
        $this->assertEquals($violations->count(), 1, '$messageNotSet will be added by empty $data');

        $envelop = new ReceiveOperation('operation_id', 'operation_code', [['foo' => 'bar']]);
        $violations = $this->validator->validate($envelop, new ConstraintReceiveOperation());
        assert($violations instanceof ConstraintViolationList);
        $this->assertEquals($violations->count(), 2, 'Prev error + syncId is not set');

        $envelop = new ReceiveOperation('operation_id', '', [['syncId' => 'syncId_1']]);
        $violations = $this->validator->validate($envelop, new ConstraintReceiveOperation());
        assert($violations instanceof ConstraintViolationList);
        $this->assertEquals($violations->count(), 3, 'Prev error + operationCode is not set');

        $envelop = new ReceiveOperation('', 'operation_code', [['syncId' => 'syncId_1']]);
        $violations = $this->validator->validate($envelop, new ConstraintReceiveOperation());
        assert($violations instanceof ConstraintViolationList);
        $this->assertEquals($violations->count(), 4, 'Prev error + operationId is not set');
    }

    /**
     * @test
     */
    public function validate_negative_value_type(): void
    {
        $this->expectException(UnexpectedTypeException::class);
        $this->validator->validate(
            new class
            {
            },
            new ConstraintReceiveOperation()
        );
    }

    /**
     * @test
     */
    public function validate_negative_constraint_type(): void
    {
        $this->expectException(UnexpectedTypeException::class);
        $this->validator->validate(
            new ReceiveOperation('operation_id', 'operation_code', []),
            new class extends Constraint
            {
            },
        );
    }

    protected function createValidator(): ConstraintReceiveOperationValidator
    {
        return new ConstraintReceiveOperationValidator();
    }
}
