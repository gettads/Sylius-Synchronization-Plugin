<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\DoctrineService;

use Doctrine\ORM\EntityManagerInterface;
use Gtt\SynchronizationPlugin\Contract\SyncableEntityInterface;
use Gtt\SynchronizationPlugin\Entity\SynchronizationInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

class SynchronizationHydrator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SynchronizationAttributeDataExtractor $extractor,
    ) {
    }

    public function hydrate(ResourceInterface $resource): void
    {
        if (!$resource instanceof SyncableEntityInterface) {
            return;
        }

        $operations = $this->extractor->extractOperationCodes($resource);

        if ($operations === []) {
            return;
        }

        $resource->setSynchronizations(
            $this->entityManager
                ->getRepository(SynchronizationInterface::class)
                ->findBy(['syncId' => $resource->getSyncId(), 'operationCode' => $operations])
        );
    }
}
