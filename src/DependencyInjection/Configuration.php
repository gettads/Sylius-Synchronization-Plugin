<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public const NODE_ROOT = 'sync_bundle';

    public const TEMPLATES_DIR = __DIR__ . '/../Resources/views';
    public const API_RESOURCES_DIR = __DIR__ . '/../Resources/api_resources';
    public const TRANSLATIONS_DIR = __DIR__ . '/../Resources/translations';
    public const DEFAULT_LOCALE = 'en';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        return new TreeBuilder(self::NODE_ROOT);
    }
}
