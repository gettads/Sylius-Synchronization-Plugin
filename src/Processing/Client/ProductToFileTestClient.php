<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\Processing\Client;

use Gtt\SynchronizationPlugin\Contract\SyncableEntityInterface;
use Gtt\SynchronizationPlugin\DoctrineEventListener\DTO\EntityChangeCollectionDto;
use Gtt\SynchronizationPlugin\DoctrineEventListener\DTO\EntityChangeDto;
use Gtt\SynchronizationPlugin\DTO\Contract\TransferableOutputInterface;
use Gtt\SynchronizationPlugin\DTO\OutputProductCollectionToFileTestDto;
use Gtt\SynchronizationPlugin\DTO\OutputProductToFileTestDto;
use Gtt\SynchronizationPlugin\DTO\OutputProductVariantToFileTestDto;
use Gtt\SynchronizationPlugin\Entity\SynchronizationInterface;
use Gtt\SynchronizationPlugin\Exception\SynchronizationInvalidOutputException;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Product\Model\ProductVariantTranslationInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Throwable;

class ProductToFileTestClient extends BaseSynchronizationOutputClient implements SynchronizationOutputClientInterface
{
    public const CODE = 'PRODUCT_TO_FILE_TEST_UPDATE';

    public const TYPE = self::TYPE_PRODUCT;

    public const CRUD_TYPES = SynchronizationClientInterface::CRUD_TYPES_ALL;

    public function isSupported(EntityChangeCollectionDto $dto): bool
    {
        if (!parent::isSupported($dto)) {
            return false;
        }

        foreach ($dto->getChanges() as $change) {
            $resource = $change->getResource();

            if (
                $resource instanceof ProductInterface
                || $resource instanceof ProductVariantInterface
                || $resource instanceof ProductVariantTranslationInterface
                || $resource instanceof ChannelPricingInterface
            ) {
                return true;
            }
        }

        return false;
    }

    /** @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter */
    public function buildTransferableOutput(
        EntityChangeCollectionDto $appliedOnlyChanges,
        EntityChangeCollectionDto $allChronologyChanges,
        SynchronizationInterface $synchronization,
    ): ?TransferableOutputInterface {
        if (!$this->isSupported($appliedOnlyChanges)) {
            //here could be custom logic for checking by $allChronologyChanges state
            return null;
        }

        try {
            $output = $this->createEnvelop($synchronization);

            foreach ($appliedOnlyChanges->getChanges() as $item) {
                assert($item instanceof EntityChangeDto);
                $resource = $item->getResource();

                if ($this->skipBySyncId($resource, $synchronization)) {
                    continue;
                }

                if (
                    $resource instanceof ProductInterface
                    && $resource instanceof SyncableEntityInterface
                ) {
                    $this->updateOutputByProduct($output, $resource);
                }

                if ($resource instanceof ProductVariantInterface) {
                    $this->updateOutputByProductVariant($output, $resource);
                }

                if ($resource instanceof ProductVariantTranslationInterface) {
                    $this->updateOutputByProductVariantTranslation($output, $resource);
                }

                if ($resource instanceof ChannelPricingInterface) {
                    $this->updateOutputByChannelPricing($output, $resource);
                }
            }

            assert($output instanceof OutputProductCollectionToFileTestDto);

            foreach ($output->getData() as $item) {
                assert($item instanceof OutputProductToFileTestDto);
                $item->setVariants(array_values($item->getVariants()));
            }

            $output->setData(array_values($output->getData()));
        } catch (Throwable $exception) {
            throw new SynchronizationInvalidOutputException(
                $exception->getMessage(),
                $exception->getCode(),
                $exception->getPrevious(),
            );
        }

        return $output;
    }

    public function getTransferEnvelopDtoClass(): string
    {
        return OutputProductCollectionToFileTestDto::class;
    }

    public function getTransferItemDtoClass(): string
    {
        return OutputProductToFileTestDto::class;
    }

    public function synchronizeOutput(TransferableOutputInterface $dto): void
    {
        $this->logger->info($this->serializer->serialize($dto, JsonEncoder::FORMAT));
    }

    private function createEnvelop(SynchronizationInterface $synchronization): OutputProductCollectionToFileTestDto
    {
        return new OutputProductCollectionToFileTestDto(
            $synchronization->getOperationId(),
            $synchronization->getOperationCode(),
            [],
        );
    }

    private function updateOutputByProduct(
        OutputProductCollectionToFileTestDto $out,
        ProductInterface & SyncableEntityInterface $resource,
    ): OutputProductToFileTestDto {
        $syncId = $resource->getSyncId();
        $productDto = $out->getFromData($syncId) ?? new OutputProductToFileTestDto($syncId);

        assert($productDto instanceof OutputProductToFileTestDto);

        $productDto->setIsEnabled($resource->isEnabled());

        if ($out->getFromData($syncId) === null) {
            $out->addToData($productDto);
        }

        return $productDto;
    }

    private function updateOutputByProductVariant(
        OutputProductCollectionToFileTestDto $out,
        ProductVariantInterface $resource
    ): OutputProductVariantToFileTestDto {
        $product = $resource->getProduct();
        assert($product instanceof ProductInterface && $product instanceof SyncableEntityInterface);
        $productDto = $this->updateOutputByProduct($out, $product);

        $variant = $productDto->getFromVariants($resource->getCode()) ?? new OutputProductVariantToFileTestDto();
        $variant->setCode($resource->getCode());
        $variant->setIsEnabled($resource->isEnabled());
        $variant->setOnStockCount($resource->getOnHand());
        $variant->setProductId($resource->getProduct()->getId());

        if ($resource->getChannelPricings()->current() !== false) {
            $variant->setPrice((int)$resource->getChannelPricings()->current()->getPrice());
        }

        if ($productDto->getFromVariants($variant->getCode()) === null) {
            $productDto->addToVariants($variant);
        }

        return $variant;
    }

    private function updateOutputByProductVariantTranslation(
        OutputProductCollectionToFileTestDto $out,
        ProductVariantTranslationInterface $resource
    ): OutputProductVariantToFileTestDto {
        $productVariantEntity = $resource->getTranslatable();
        assert($productVariantEntity instanceof ProductVariantInterface);
        $productEntity = $productVariantEntity->getProduct();
        assert($productEntity instanceof ProductInterface && $productEntity instanceof SyncableEntityInterface);

        $this->updateOutputByProduct($out, $productEntity);
        $variant = $this->updateOutputByProductVariant($out, $productVariantEntity);
        $variant->addToLocalizations($resource->getLocale(), ['name' => $resource->getName()]);

        return $variant;
    }

    private function updateOutputByChannelPricing(
        OutputProductCollectionToFileTestDto $out,
        ChannelPricingInterface $resource
    ): OutputProductVariantToFileTestDto {
        $product = $resource->getProductVariant()->getProduct();
        assert($product instanceof ProductInterface && $product instanceof SyncableEntityInterface);
        $this->updateOutputByProduct($out, $product);

        $variant = $this->updateOutputByProductVariant($out, $resource->getProductVariant());
        $variant->setPrice($resource->getPrice());

        return $variant;
    }
}
