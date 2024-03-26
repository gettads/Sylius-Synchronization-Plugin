<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\DoctrineEventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Gtt\SynchronizationPlugin\DoctrineEventListener\DTO\EntityChangeDto;
use Gtt\SynchronizationPlugin\DoctrineEventListener\Service\ChangesApplicatorService;
use Gtt\SynchronizationPlugin\Processing\SyncProcessor;
use Sylius\Component\Resource\Model\ResourceInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

class EntitySubscriber implements EventSubscriber
{
    public function __construct(
        private SyncProcessor $syncProcessor,
        private ChangesApplicatorService $applicatorService,
        private RequestStack $requestStack,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $this->applicatorService->refresh();

        if ($this->syncProcessor->isIncomingOperation()) {
            // importing data should not be sent back (as import source is often also an export target)
            return;
        }

        $unit = $args->getObjectManager()->getUnitOfWork();

        foreach ($unit->getScheduledEntityInsertions() as $entity) {
            if (!$entity instanceof ResourceInterface) {
                continue;
            }

            $changes = $this->applicatorService->prepareValidChanges($unit, $entity);
            $this->applicatorService->applyChanges(
                new EntityChangeDto($entity, $changes, EntityChangeDto::CRUD_TYPE_CREATE),
            );
        }

        foreach ($unit->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof ResourceInterface) {
                continue;
            }

            $changes = $this->applicatorService->prepareValidChanges($unit, $entity);
            $this->applicatorService->applyChanges(
                new EntityChangeDto($entity, $changes, EntityChangeDto::CRUD_TYPE_UPDATE),
            );
        }

        foreach ($unit->getScheduledEntityDeletions() as $entity) {
            if (!$entity instanceof ResourceInterface) {
                continue;
            }

            $this->applicatorService->applyChanges(
                new EntityChangeDto($entity, [], EntityChangeDto::CRUD_TYPE_DELETE)
            );
        }
    }

    // @phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->syncProcessor->isIncomingOperation()) {
            // importing data should not be sent back (as import source is often also an export target)
            return;
        }

        if (!$this->applicatorService->hasChanges()) {
            return;
        }

        $errors = $this->syncProcessor->synchronizeOutcoming(
            $this->applicatorService->getOnlyAppliedChanges(),
            $this->applicatorService->getAllChronologyChanges(),
        );

        foreach ($errors as $error) {
            $bag = $this->requestStack->getSession()->getBag('flashes');
            assert($bag instanceof FlashBagInterface);
            $bag->add('error', 'Synchronization: ' . $error);
        }
    }

    /**
     * @return array<string>
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::onFlush,
            Events::postFlush,
        ];
    }
}
