<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gtt\SynchronizationPlugin\Api\Handler\ReceiveOperationHandler;
use Gtt\SynchronizationPlugin\Api\Handler\SyncStatusOperationHandler;
use Gtt\SynchronizationPlugin\DoctrineEventListener\EntitySubscriber;
use Gtt\SynchronizationPlugin\DoctrineEventListener\Service\ChangesApplicatorService;
use Gtt\SynchronizationPlugin\ExceptionListener\DbalDriverExceptionListener;
use Gtt\SynchronizationPlugin\ExceptionListener\ErrorsInRequestListener;
use Gtt\SynchronizationPlugin\Grid\SynchronizationGrid;
use Gtt\SynchronizationPlugin\Menu\AdminMenuListener;
use Gtt\SynchronizationPlugin\Processing\Client\SynchronizationInputClientInterface;
use Gtt\SynchronizationPlugin\Processing\Client\SynchronizationOutputClientInterface;
use Gtt\SynchronizationPlugin\Processing\SyncProcessor;
use Sylius\Bundle\AdminBundle\Menu\MainMenuBuilder;

return function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();
    $services->defaults()
        ->autowire(true)
        ->bind('string $envMode', param('kernel.environment'))
    ;

    $services->set(AdminMenuListener::class)
        ->public()
        ->autowire(true)
        ->tag(
            'kernel.event_listener',
            ['event' => MainMenuBuilder::EVENT_NAME, 'method' => AdminMenuListener::METHOD]
        );

    $services->set(SynchronizationGrid::class)->public()->autowire(true)->tag('sylius.grid');

    $services->set(EntitySubscriber::class)
        ->public()
        ->autowire(true)
        ->tag('doctrine.event_subscriber');

    $services->set(SyncProcessor::class)->public()->arg('$requestStack', service('request_stack'));

    $services->set(ChangesApplicatorService::class)->autowire(true)->public();
    $services->set(DbalDriverExceptionListener::class)->autowire(true)->public()->tag('kernel.event_listener');
    $services->set(ErrorsInRequestListener::class)
        ->autowire(true)
        ->public()
        ->arg('$requestStack', service('request_stack'))
        ->tag(
            'kernel.event_listener',
            ['event' => 'kernel.response', 'method' => ErrorsInRequestListener::METHOD]
        );

    $services->instanceof(SynchronizationInputClientInterface::class)->tag(SyncProcessor::CLIENTS_INTPUT_SERVICE_TAG);
    $services->instanceof(SynchronizationOutputClientInterface::class)->tag(SyncProcessor::CLIENTS_OUTPUT_SERVICE_TAG);

    $services->set(SyncStatusOperationHandler::class)
        ->autowire(true)
        ->public()
        ->tag('messenger.message_handler', ['bus' => 'sylius.command_bus'])
        ->tag('messenger.message_handler', ['bus' => 'sylius_default.bus']);

    $services->set(ReceiveOperationHandler::class)
        ->autowire(true)
        ->public()
        ->args(['$requestStack' => service('request_stack')])
        ->tag('messenger.message_handler', ['bus' => 'sylius.command_bus'])
        ->tag('messenger.message_handler', ['bus' => 'sylius_default.bus']);

    $services
        ->load('Gtt\SynchronizationPlugin\DoctrineService\\', __DIR__ . '/../../../src/DoctrineService')
        ->load('Gtt\SynchronizationPlugin\Processing\Client\\', __DIR__ . '/../../../src/Processing/Client')
        ->args([
            '$httpClient' => service('sylius.http_client'),
        ])
        ->public()
        ->autowire(true);
};
