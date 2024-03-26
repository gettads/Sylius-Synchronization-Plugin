<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\ExceptionListener;

use Doctrine\DBAL\Exception\DriverException;
use Gtt\SynchronizationPlugin\DoctrineEventListener\Service\ChangesApplicatorService;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class DbalDriverExceptionListener
{
    public function __construct(private ChangesApplicatorService $applicatorService)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof DriverException) {
            $this->applicatorService->refresh(true);
        }
    }
}
