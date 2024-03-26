<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\Exception;

use Exception;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SynchronizationCorruptedException extends Exception
{
    private const PRE_MESSAGE = 'Synchronization process was corrupted.';

    public function __construct(
        string $message = self::PRE_MESSAGE,
        int $code = Response::HTTP_INTERNAL_SERVER_ERROR,
        ?Throwable $previous = null
    ) {
        parent::__construct(self::PRE_MESSAGE . ' ' . $message, $code, $previous);
    }
}
