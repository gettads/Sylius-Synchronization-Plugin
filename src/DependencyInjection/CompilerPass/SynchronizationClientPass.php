<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\DependencyInjection\CompilerPass;

use Gtt\SynchronizationPlugin\Processing\SyncProcessor;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class SynchronizationClientPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $processorDefinition = $container->findDefinition(SyncProcessor::class);

        foreach (array_keys($container->findTaggedServiceIds(SyncProcessor::CLIENTS_INTPUT_SERVICE_TAG)) as $id) {
            $processorDefinition->addMethodCall(SyncProcessor::ADD_INPUT_CLIENT_METHOD, [new Reference($id)]);
        }

        foreach (array_keys($container->findTaggedServiceIds(SyncProcessor::CLIENTS_OUTPUT_SERVICE_TAG)) as $id) {
            $processorDefinition->addMethodCall(SyncProcessor::ADD_OUTPUT_CLIENT_METHOD, [new Reference($id)]);
        }

        $processorDefinition->addMethodCall(SyncProcessor::CHECK_ENTITIES_BY_CLIENTS_METHOD, []);
    }
}
