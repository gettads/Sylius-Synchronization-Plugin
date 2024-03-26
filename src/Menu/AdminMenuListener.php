<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

class AdminMenuListener
{
    public const METHOD = 'addAdminMenuItems';

    public function addAdminMenuItems(MenuBuilderEvent $event): void
    {
        $logsMenu = $event->getMenu()->getChild('logs');
        $logsMenu ??= $event->getMenu()->addChild('logs')->setLabel('app.ui.logs');

        $logsMenu->addChild('sync_logs', ['route' => 'app_admin_synchronization_index'])
            ->setLabel('app.ui.menu_title')
            ->setLabelAttribute('icon', 'sync icon');
    }
}
