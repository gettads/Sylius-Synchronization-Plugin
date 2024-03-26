<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\DependencyInjection;

use Gtt\SynchronizationPlugin\Entity\Synchronization;
use Gtt\SynchronizationPlugin\Entity\SynchronizationInterface;
use Gtt\SynchronizationPlugin\Exception\SynchronizationInvalidInputException;
use Gtt\SynchronizationPlugin\Processing\Client\SynchronizationInputClientInterface;
use Gtt\SynchronizationPlugin\Processing\Client\SynchronizationOutputClientInterface;
use Gtt\SynchronizationPlugin\Processing\SyncProcessor;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpFoundation\Response;

class SynchronizationPluginExtension extends Extension implements PrependExtensionInterface
{
    public const RESOURCE_KEY = 'app.synchronization';

    /**
     * @inheritDoc
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $this->processConfiguration($this->getConfiguration([], $container), $configs);

        $loader = new Loader\PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.php');

        $container
            ->registerForAutoconfiguration(SynchronizationInputClientInterface::class)
            ->addTag(SyncProcessor::CLIENTS_INTPUT_SERVICE_TAG);

        $container
            ->registerForAutoconfiguration(SynchronizationOutputClientInterface::class)
            ->addTag(SyncProcessor::CLIENTS_OUTPUT_SERVICE_TAG);
    }

    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('sylius_resource')) {
            $container->prependExtensionConfig(
                'sylius_resource',
                [
                    'resources' => [
                        self::RESOURCE_KEY => [
                            'classes' => [
                                'model' => Synchronization::class,
                                'interface' => SynchronizationInterface::class,
                            ],
                        ],
                    ],
                ]
            );
        }

        if ($container->hasExtension('api_platform')) {
            $container->prependExtensionConfig(
                'api_platform',
                [
                    'mapping' => [
                        'paths' => [Configuration::API_RESOURCES_DIR],
                    ],
                ]
            );
        }

        if ($container->hasExtension('twig')) {
            $container->prependExtensionConfig(
                'twig',
                ['paths' => [Configuration::TEMPLATES_DIR => 'SynchronizationPlugin']],
            );
        }

        if ($container->hasExtension('framework')) {
            $configFramefork = $container->getExtensionConfig('framework');
            $container->prependExtensionConfig(
                'framework',
                [
                    'default_locale' => Configuration::DEFAULT_LOCALE,
                    'translator' => [
                        'paths' => array_merge(
                            $configFramefork['translator']['paths'] ?? [],
                            [Configuration::TRANSLATIONS_DIR]
                        ),
                        'fallbacks' => [Configuration::DEFAULT_LOCALE],
                    ],
                ]
            );
        }

        if ($container->hasExtension('api_platform')) {
            $container->prependExtensionConfig(
                'api_platform',
                ['exception_to_status' => [SynchronizationInvalidInputException::class => Response::HTTP_BAD_REQUEST]],
            );
        }
    }

    /**
     * @inheritDoc
     */
    // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
    public function getConfiguration(array $config, ContainerBuilder $container): ConfigurationInterface
    {
        return new Configuration();
    }
}
