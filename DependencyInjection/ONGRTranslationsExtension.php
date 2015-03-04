<?php

/*
 * This file is part of the ONGR package.
 *
 * (c) NFQ Technologies UAB <info@nfq.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ONGR\TranslationsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages bundle configuration.
 */
class ONGRTranslationsExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');
        $loader->load('filters_container.yml');

        $container->setParameter('ongr_translations.managed_locales', $config['managed_locales']);
        $container->setParameter('ongr_translations.formats', $config['formats']);
        $container->setParameter('ongr_translations.domains', $config['domains']);
        $this->validateBundles($container, $config['bundles']);

        $this->setElasticsearchStorage($config['repository'], $container);
        $this->setFiltersManager($config['repository'], $container);
        $this->setControllerManager($config['repository'], 'rest', $container);
        $this->setControllerManager($config['repository'], 'list', $container);
    }

    /**
     * Sets elasticsearch storage for translations.
     *
     * @param string           $repositoryId Elasticsearch repository id.
     * @param ContainerBuilder $container    Service container.
     */
    private function setElasticsearchStorage($repositoryId, ContainerBuilder $container)
    {
        $definition = new Definition(
            'ONGR\TranslationsBundle\Storage\ElasticsearchStorage',
            [
                new Reference($repositoryId),
            ]
        );

        $container->setDefinition('ongr_translations.storage', $definition);
    }

    /**
     * Adds filter manager for displaying translations gui.
     *
     * @param string           $repositoryId Elasticsearch repository id.
     * @param ContainerBuilder $container    Service container.
     */
    private function setFiltersManager($repositoryId, ContainerBuilder $container)
    {
        $definition = new Definition(
            'ONGR\FilterManagerBundle\Search\FiltersManager',
            [
                new Reference('ongr_translations.filters_container'),
                new Reference($repositoryId),
            ]
        );

        $container->setDefinition('ongr_translations.filters_manager', $definition);
    }

    /**
     * Injects elasticsearch repository to controller and sets it into service container.
     *
     * @param string           $repositoryId   Elasticsearch repository id.
     * @param string           $controllerName Controller name to which add repository.
     * @param ContainerBuilder $container      Service container.
     */
    private function setControllerManager($repositoryId, $controllerName, ContainerBuilder $container)
    {
        $definition = new Definition(
            sprintf('ONGR\TranslationsBundle\Controller\%sController', ucfirst($controllerName)),
            [
                new Reference($repositoryId),
            ]
        );
        $definition->addMethodCall('setContainer', [new Reference('service_container')]);

        $container->setDefinition(sprintf('ongr_translations.controller.%s', $controllerName), $definition);
    }

    /**
     * Validates configured bundles and sets into service container as parameter.
     *
     * @param ContainerBuilder $container Service container.
     * @param array            $bundles   Bundles array.
     *
     * @throws InvalidConfigurationException
     */
    private function validateBundles($container, $bundles)
    {
        foreach ($bundles as $bundle) {
            try {
                $reflection = new \ReflectionClass($bundle);
            } catch (\ReflectionException $e) {
                throw new InvalidConfigurationException(
                    "Invalid bundle namespace {$bundle}. Error: {$e->getMessage()}"
                );
            }
        }
        $container->setParameter('ongr_translations.bundles', $bundles);
    }
}
