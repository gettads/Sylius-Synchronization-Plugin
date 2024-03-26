<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\DoctrineEventListener\Service;

use Doctrine\ORM\UnitOfWork;
use Gtt\SynchronizationPlugin\Contract\Attribute\AsSyncableEntity;
use Gtt\SynchronizationPlugin\DoctrineEventListener\DTO\EntityChangeCollectionDto;
use Gtt\SynchronizationPlugin\DoctrineEventListener\DTO\EntityChangeDto;
use Gtt\SynchronizationPlugin\DoctrineService\SynchronizationAttributeDataExtractor;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Sylius\Component\Resource\Model\ResourceInterface;

class ChangesApplicatorService
{
    private const IGNORED_ATTRIBUTES = ['updatedAt'];

    private const INDEX_OLD = 0;

    private const INDEX_NEW = 1;

    /**
     * @var array<string, array<string, array<string, array<int|string|bool|float|null>>>>
     */
    private array $storage = [];

    public function __construct(
        private LoggerInterface $logger,
        private SynchronizationAttributeDataExtractor $extractor,
        private EntityChangeCollectionDto $chronologyCollection = new EntityChangeCollectionDto(true),
        private EntityChangeCollectionDto $changes = new EntityChangeCollectionDto(false),
    )
    {
    }

    public function refresh(?bool $isDeep = false): void
    {
        $this->changes = new EntityChangeCollectionDto(false);

        if ($isDeep) {
            $this->storage = [];
        }
    }

    public function getOnlyAppliedChanges(): EntityChangeCollectionDto
    {
        return $this->changes;
    }

    public function hasChanges(): bool
    {
        return $this->changes->getChanges() !== [];
    }

    public function getAllChronologyChanges(): EntityChangeCollectionDto
    {
        return $this->chronologyCollection;
    }

    public function applyChanges(EntityChangeDto $changeDto): void
    {
        if (!$this->extractor->hasSyncableAttributeAnnotation($changeDto->getResource())) {
            return;
        }

        $crudType = $changeDto->getType();

        if ($crudType === EntityChangeDto::CRUD_TYPE_DELETE) {
            $changeDto->setResource($this->cloneResourceOnDelete($changeDto->getResource()));
            $changeDto->setChanges(
                ['id' => [self::INDEX_OLD => $changeDto->getResource()->getId(), self::INDEX_NEW => null]],
            );
        }

        $key = $this->getKeyIdentifier($changeDto);
        $currentChanges = $changeDto->getChanges();

        $this->chronologyCollection->add($changeDto);

        foreach ($changeDto->getChanges() as $attribute => $oldNewData) {
            assert(is_array($oldNewData));
            [$old, $new] = array_pad($oldNewData, 2, null);
            $old = $old instanceof ResourceInterface ? $old->getId() : $old;
            $new = $new instanceof ResourceInterface ? $new->getId() : $new;

            $prevValue = $this->storage[$crudType][$key][$attribute] ?? 'not-' . serialize($new);

            if (in_array($attribute, self::IGNORED_ATTRIBUTES, true) || $prevValue === $new) {
                unset($currentChanges[$attribute]);

                continue;
            }

            $this->storage[$crudType][$key][$attribute] = $new;
        }

        if ($currentChanges !== []) {
            $this->changes->add(new EntityChangeDto($changeDto->getResource(), $currentChanges, $crudType), $key);
        }
    }

    /**
     * @return array<string, array<int, int|string|float|bool|object|null>>
     */
    public function prepareValidChanges(UnitOfWork $unit, ResourceInterface $entity): array
    {
        $changeSet = $unit->getEntityChangeSet($entity);

        $preparedChanges = [];

        foreach ($changeSet as $key => $value) {
            if (!is_string($key) || !property_exists($entity, $key)) {
                $this->logger->log(
                    LogLevel::ERROR,
                    sprintf(
                        'ChangeSet has incorrect property %s for entity %s, ID: %s',
                        $key,
                        $entity::class,
                        $entity->getId(),
                    ),
                );

                continue;
            }

            if (is_object($value)) {
                $this->logger->log(
                    LogLevel::ERROR,
                    sprintf(
                        'ChangeSet has incorrect value (object "%s" for entity %s, ID: %s)',
                        $value::class,
                        $entity::class,
                        $entity->getId(),
                    ),
                );

                continue;
            }

            if (!isset($value[self::INDEX_OLD], $value[self::INDEX_NEW])) {
                $this->logger->log(
                    LogLevel::ERROR,
                    'ChangeSet has incorrect indexes for entity ' . $entity::class,
                );

                continue;
            }

            $preparedChanges[$key] = [
                self::INDEX_OLD => $value[self::INDEX_OLD],
                self::INDEX_NEW => $value[self::INDEX_NEW],
            ];
        }

        return $preparedChanges;
    }

    private function getKeyIdentifier(EntityChangeDto $changeDto): string
    {
        $resource = $changeDto->getResource();

        return $resource::class . '::' . $resource->getId();
    }

    private function cloneResourceOnDelete(ResourceInterface $resource): ResourceInterface
    {
        $relationsMap = $this->extractor->getRelationsToSyncables($resource);
        $clonedResource = clone $resource;

        foreach ($this->extractor->getRelationsNamesToSyncables($resource) as $path) {
            $object = $clonedResource;

            if ($path === AsSyncableEntity::ROOT_PATH) {
                continue;
            }

            foreach ($this->extractor->explode($path) as $subPath) {
                $setter = 'set' . ucfirst($subPath);
                $getter = 'get' . ucfirst($subPath);
                $object->{$setter}(clone $relationsMap[$subPath]);
                $object = $object->{$getter}();
            }
        }

        return $clonedResource;
    }
}
