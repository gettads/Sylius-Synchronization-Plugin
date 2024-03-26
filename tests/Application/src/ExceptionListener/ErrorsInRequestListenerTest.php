<?php

declare(strict_types=1);

namespace Tests\Gtt\SynchronizationPlugin\Application\ExceptionListener;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Gtt\SynchronizationPlugin\ExceptionListener\ErrorsInRequestListener;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Throwable;

class ErrorsInRequestListenerTest extends TestCase
{
    /**
     * @test
     * @dataProvider provideErrors
     */
    public function All_errors_are_prepared(array $errors, array $expectedResult): void
    {
        $reflectionClass = new ReflectionClass(ErrorsInRequestListener::class);
        $prepareCodeAndMessage = $reflectionClass->getMethod('prepareCodeAndMessage');
        $prepareCodeAndMessage->setAccessible(true);
        $result = $prepareCodeAndMessage->invoke($this->createErrorsInRequestListener(), $errors);
        self::assertSame($expectedResult, $result);
    }

    public function provideErrors(): array
    {
        return [
            'no errors' => [
                [],
                [Response::HTTP_NO_CONTENT, null],
            ],
            'internal server error' => [
                [Response::HTTP_INTERNAL_SERVER_ERROR => []],
                [Response::HTTP_INTERNAL_SERVER_ERROR, 'Internal server error.'],
            ],
            'internal server error with specific code' => [
                [Response::HTTP_NOT_IMPLEMENTED => []],
                [Response::HTTP_INTERNAL_SERVER_ERROR, 'Internal server error.'],
            ],
            'unexpected value exception' => [
                [Response::HTTP_CONFLICT => [UnexpectedValueException::class => 'foo', Throwable::class => 'bar']],
                [Response::HTTP_BAD_REQUEST, 'foo; bar'],
            ],
            'other exceptions' => [
                [Response::HTTP_CONFLICT => [LogicException::class => 'foo', Throwable::class => 'bar']],
                [Response::HTTP_CONFLICT, 'foo; bar'],
            ],
            'multiple errors of same HTTP code group with highest code later' => [
                [
                    Response::HTTP_FORBIDDEN => [InvalidArgumentException::class => 'foo'],
                    Response::HTTP_CONFLICT => [RuntimeException::class => 'bar', InvalidArgumentException::class => 'baz'],
                ],
                [Response::HTTP_BAD_REQUEST, 'foo; bar; baz'],
            ],
            'multiple errors with different HTTP code groups with highest code later' => [
                [
                    Response::HTTP_FOUND => [InvalidArgumentException::class => 'foo'],
                    Response::HTTP_CONFLICT => [RuntimeException::class => 'bar', InvalidArgumentException::class => 'baz'],
                ],
                [Response::HTTP_CONFLICT, 'foo; bar; baz'],
            ],
        ];
    }

    private function createErrorsInRequestListener(): ErrorsInRequestListener
    {
        return new class extends ErrorsInRequestListener
        {
            public function __construct()
            {
            }
        };
    }
}
