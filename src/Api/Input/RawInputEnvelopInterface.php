<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\Api\Input;

interface RawInputEnvelopInterface
{
    /**
     * @return array<string|int, array|\ArrayObject|bool|float|int|string|null>
     */
    public function getData(): array;

    public function getOperationId(): string;

    public function getOperationCode(): string;
}
