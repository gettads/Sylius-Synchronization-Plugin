<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\Exception;

use Exception;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SynchronizationInvalidOutputException extends Exception
{
    private const PRE_MESSAGE = 'Outcoming synchronization data can not be mapped.';

    public function __construct(
        string $message = self::PRE_MESSAGE,
        int $code = Response::HTTP_BAD_REQUEST,
        ?Throwable $previous = null,
    ) {
        parent::__construct(self::PRE_MESSAGE . ' ' . $message, $code, $previous);
    }
}
