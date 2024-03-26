<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\Grid;

use Gtt\SynchronizationPlugin\Entity\Synchronization;
use Gtt\SynchronizationPlugin\Entity\SynchronizationInterface;
use Gtt\SynchronizationPlugin\Processing\SyncProcessor;
use Sylius\Bundle\GridBundle\Builder\Action\ShowAction;
use Sylius\Bundle\GridBundle\Builder\ActionGroup\ItemActionGroup;
use Sylius\Bundle\GridBundle\Builder\Field\DateTimeField;
use Sylius\Bundle\GridBundle\Builder\Field\StringField;
use Sylius\Bundle\GridBundle\Builder\Filter\DateFilter;
use Sylius\Bundle\GridBundle\Builder\Filter\SelectFilter;
use Sylius\Bundle\GridBundle\Builder\Filter\StringFilter;
use Sylius\Bundle\GridBundle\Builder\GridBuilderInterface;
use Sylius\Bundle\GridBundle\Grid\AbstractGrid;
use Sylius\Bundle\GridBundle\Grid\ResourceAwareGridInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class SynchronizationGrid extends AbstractGrid implements ResourceAwareGridInterface
{
    public function __construct(private TranslatorInterface $translator, private SyncProcessor $processor)
    {
    }

    public function buildGrid(GridBuilderInterface $gridBuilder): void
    {
        $gridBuilder->orderBy('createdAt', 'desc');

        $gridBuilder
            ->addField(StringField::create('type')->setLabel('app.sync.type'))
            ->addField(StringField::create('syncId')->setLabel('app.sync.sync_id'))
            ->addField(StringField::create('flowType')->setLabel('app.sync.flow_type'))
            ->addField(StringField::create('operationCode')->setLabel('app.sync.operation_code'))
            ->addField(StringField::create('operationId')->setLabel('app.sync.operation_id'))
            ->addField(StringField::create('status')->setLabel('app.sync.status'))
            ->addField(
                DateTimeField::create('createdAt')
                    ->setLabel('app.sync.created_at')
                    ->setSortable(true, 'createdAt')
            );

        $gridBuilder
            ->addActionGroup(
                ItemActionGroup::create(
                    ShowAction::create(),
                )
            );

        $gridBuilder
            ->addFilter(DateFilter::create('createdAt')->setLabel('app.sync.created_at'))
            ->addFilter(StringFilter::create('syncId', null, 'equal')->setLabel('app.sync.sync_id'))
            ->addFilter(StringFilter::create('operationId', null, 'equal')->setLabel('app.sync.operation_id'))
            ->addFilter(StringFilter::create('operationCode')->setLabel('app.sync.operation_code'))
            ->addFilter(
                SelectFilter::create('flowType', $this->createFlowTypeChoices())
                    ->setFormOptions(
                        [
                        'choices' => $this->createFlowTypeChoices(),
                        'multiple' => true,
                        'attr' => ['class' => 'fluid search selection multiple'],
                        ]
                    )
                    ->setLabel('app.sync.flow_type')
            )
            ->addFilter(
                SelectFilter::create('type', $this->createTypeChoices())
                    ->setFormOptions(
                        [
                        'choices' => $this->createTypeChoices(),
                        'multiple' => true,
                        'attr' => ['class' => 'fluid search selection multiple'],
                        ]
                    )
                    ->setLabel('app.sync.type')
            )
            ->addFilter(
                SelectFilter::create('status', $this->createStatusChoices())
                    ->setFormOptions(
                        [
                        'choices' => $this->createStatusChoices(),
                        'multiple' => true,
                        'attr' => ['class' => 'fluid search selection multiple'],
                        ]
                    )
                    ->setLabel('app.sync.status')
            );
    }

    public function getResourceClass(): string
    {
        return Synchronization::class;
    }

    public static function getName(): string
    {
        return 'app_sync';
    }

    /**
     * @return array<string, string>
     */
    private function createTypeChoices(): array
    {
        $data = [];

        foreach ([...$this->processor->getInputClients(), ...$this->processor->getOutputClients()] as $client) {
            $label = $this->translator->trans('app.sync.types.' . $client->getType());
            $data[$label] = $client->getType();
        }

        ksort($data);

        return $data;
    }

    /**
     * @return array<string, string>
     */
    private function createStatusChoices(): array
    {
        return [
            $this->translator->trans('app.sync.statuses.before_sync')
                => SynchronizationInterface::STATUS_BEFORE_SYNC,
            $this->translator->trans('app.sync.statuses.error_on_sync_mapping')
                => SynchronizationInterface::STATUS_ERROR_ON_SYNC_MAPPING,
            $this->translator->trans('app.sync.statuses.error_on_sync_transport')
                => SynchronizationInterface::STATUS_ERROR_ON_SYNC_TRANSPORT,
            $this->translator->trans('app.sync.statuses.sync_in_progress')
                => SynchronizationInterface::STATUS_SYNCHRONIZATION,
            $this->translator->trans('app.sync.statuses.sync_error')
                => SynchronizationInterface::STATUS_ERROR_SYNC,
            $this->translator->trans('app.sync.statuses.sync_ok')
                => SynchronizationInterface::STATUS_SUCCESS_SYNC,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function createFlowTypeChoices(): array
    {
        return [
            $this->translator->trans('app.sync.flow_types.incoming') => SynchronizationInterface::FLOW_TYPE_INCOMING,
            $this->translator->trans('app.sync.flow_types.outcoming') => SynchronizationInterface::FLOW_TYPE_OUTCOMING,
        ];
    }
}
