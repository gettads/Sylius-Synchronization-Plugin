<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\DTO;

class InputProductVariantFromTestDto
{
    private string $code;

    private string $productCode;

    private int $onStockCount;

    private int $price;

    private bool $isEnabled;

    /**
     * @var array<string, array<string, string>>
     */
    private array $localizations = [];

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getProductCode(): string
    {
        return $this->productCode;
    }

    public function setProductCode(string $productCode): void
    {
        $this->productCode = $productCode;
    }

    public function getOnStockCount(): int
    {
        return $this->onStockCount;
    }

    public function setOnStockCount(int $onStockCount): void
    {
        $this->onStockCount = $onStockCount;
    }

    public function getPrice(): int
    {
        return $this->price;
    }

    public function setPrice(int $price): void
    {
        $this->price = $price;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(bool $isEnabled): void
    {
        $this->isEnabled = $isEnabled;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getLocalizations(): array
    {
        return $this->localizations;
    }

    /**
     * @param array<string, array<string, string>> $localizations
     */
    public function setLocalizations(array $localizations): void
    {
        $this->localizations = $localizations;
    }

    /**
     * @param array<string, string> $translations
     */
    public function addToLocalizations(string $locale, array $translations): void
    {
        foreach ($translations as $key => $value) {
            $this->localizations[$locale][$key] = $value;
        }
    }
}
