<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin;

use Gtt\SynchronizationPlugin\DependencyInjection\CompilerPass\SynchronizationClientPass;
use Gtt\SynchronizationPlugin\DependencyInjection\SynchronizationPluginExtension;
use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SynchronizationPlugin extends Bundle
{
    use SyliusPluginTrait;

    public function getContainerExtension(): ExtensionInterface
    {
        $this->extension = new SynchronizationPluginExtension();

        return $this->extension;
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new SynchronizationClientPass());
    }
}
