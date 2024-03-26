<?php

declare(strict_types=1);

namespace Tests\Gtt\SynchronizationPlugin\Behat\Context;

use Behat\Behat\Context\Context;

final class InitContext implements Context
{
    /**
     * @Given a bundle with working health-check test
     */
    public function healthCheck(): void
    {

    }
}
