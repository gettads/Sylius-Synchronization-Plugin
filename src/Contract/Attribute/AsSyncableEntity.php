<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\Contract\Attribute;

use Attribute;
use Gtt\SynchronizationPlugin\Exception\SynchronizationConfigException;

/*
 * Operations parameter: 'way to syncable entity, like productVariant.product' => ['PRODUCT_TO_FILE', 'PRODUCT_TO_ABRA_API_UPDATE']
 * Examples:
 * #[AsSyncableEntity(operations: [AsSyncableEntity::ROOT_PATH => [ProductToFileTestClient::CODE]])] for Product (SyncableEntity) '.' (dot) is root, similar to root web domain notation
 * #[AsSyncableEntity(operations: ['translatable.product' => [ProductToFileTestClient::CODE]])] for ProductVariantTranslation will be invoked ->getTranslatable()->getProduct()
 */

#[Attribute(Attribute::TARGET_CLASS)]
class AsSyncableEntity
{
    public const ROOT_PATH = '.';

    /**
     * @param array<string, array<string>> $operations
     */
    public function __construct(private array $operations = [])
    {
        $this->checkOperationsConfig();
    }

    /**
     * @return array<string, array<string>>
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    private function checkOperationsConfig(): void
    {
        $message = 'Parameter $operations must be an array,
            like:  ["relation.to.syncableEntity" => ["SYNC_WAY_1", "SYNC_WAY_2"]]';

        foreach ($this->getOperations() as $path => $operationsArray) {
            if (
                ($path !== self::ROOT_PATH && !preg_match('/[a-z]+/i', (string)$path))
                || !is_array($operationsArray)
            ) {
                throw new SynchronizationConfigException($message);
            }

            foreach ($operationsArray as $operation) {
                if (!is_string($operation)) {
                    throw new SynchronizationConfigException($message);
                }
            }
        }
    }
}
