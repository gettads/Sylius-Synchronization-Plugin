<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\DTO;

use Gtt\SynchronizationPlugin\DTO\Contract\TransferableItemInterface;
use Gtt\SynchronizationPlugin\DTO\Contract\TransferableItemTrait;

class InputProductFromTestDto implements TransferableItemInterface
{
    use TransferableItemTrait;

    private string $code;

    private bool $isEnabled;

    /**
     * @var array<InputProductVariantFromTestDto>
     */
    private array $variants = [];

    public function __construct(string $syncId)
    {
        $this->syncId = $syncId;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getIsEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(bool $isEnabled): void
    {
        $this->isEnabled = $isEnabled;
    }

    /**
     * @return array<InputProductVariantFromTestDto>>
     */
    public function getVariants(): array
    {
        return $this->variants;
    }

    /**
     * @param array<InputProductVariantFromTestDto> $variants
     */
    public function setVariants(array $variants): void
    {
        $this->variants = $variants;
    }

    public function addToVariants(InputProductVariantFromTestDto $dto): void
    {
        $this->variants[$dto->getCode()] = $dto;
    }

    public function getFromVariants(string $code): ?InputProductVariantFromTestDto
    {
        return $this->variants[$code] ?? null;
    }
}
