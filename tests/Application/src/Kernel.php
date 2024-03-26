<?php

declare(strict_types=1);

namespace Tests\Gtt\SynchronizationPlugin\Application;

use PSS\SymfonyMockerContainer\DependencyInjection\MockerContainer;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    private const CONFIG_EXTS = '.{php,xml,yaml,yml}';

    public function boot(): void
    {
        $this->upAssertions();

        parent::boot();
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/var/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/var/log';
    }

    public function registerBundles(): iterable
    {
        foreach ($this->getConfigurationDirectories() as $confDir) {
            $bundlesFile = $confDir . '/bundles.php';
            if (false === is_file($bundlesFile)) {
                continue;
            }
            yield from $this->registerBundlesFromFile($bundlesFile);
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        foreach ($this->getConfigurationDirectories() as $confDir) {
            $this->loadRoutesConfiguration($routes, $confDir);
        }
    }

    protected function getContainerBaseClass(): string
    {
        if ($this->isTestEnvironment() && class_exists(MockerContainer::class)) {
            return MockerContainer::class;
        }

        return parent::getContainerBaseClass();
    }

    private function isTestEnvironment(): bool
    {
        return 0 === strpos($this->getEnvironment(), 'test');
    }

    private function loadRoutesConfiguration(RoutingConfigurator $routes, string $confDir): void
    {
        $routes->import($confDir . '/{routes}/*' . self::CONFIG_EXTS);
        $routes->import($confDir . '/{routes}/' . $this->environment . '/**/*' . self::CONFIG_EXTS);
        $routes->import($confDir . '/{routes}' . self::CONFIG_EXTS);
    }

    /**
     * @return BundleInterface[]
     */
    private function registerBundlesFromFile(string $bundlesFile): iterable
    {
        $contents = require $bundlesFile;
        foreach ($contents as $class => $envs) {
            if (isset($envs['all']) || isset($envs[$this->environment])) {
                yield new $class();
            }
        }
    }

    /**
     * @return array<string>
     */
    private function getConfigurationDirectories(): array
    {
        return [$this->getProjectDir() . '/config'];
    }

    private function upAssertions(): void
    {
        ini_set('zend.assertions', 1);
        ini_set('assert.exception', 1);

        assert_options(ASSERT_ACTIVE, true);
        assert_options(ASSERT_WARNING, true);

        // php.ini has config: zend.assertions=-1
        if ((int) ini_get('zend.assertions') !== 1) {
            throw new \Exception(
                'Can not enable assertions mode. Check if in php.ini is zend.assertions=-1'
                . ' and change it at least to zend.assertions=0.',
            );
        }
    }
}
