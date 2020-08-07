<?php

declare(strict_types=1);

/*
 * This file is part of the Serendipity HQ Users Bundle.
 *
 * Copyright (c) Adamo Aerendir Crespi <aerendir@serendipityhq.com>.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SerendipityHQ\Bundle\UsersBundle\DependencyInjection;

use Doctrine\ORM\Events;
use SerendipityHQ\Bundle\UsersBundle\Doctrine\UserEncodePasswordListener;
use SerendipityHQ\Bundle\UsersBundle\Manager\UsersManager;
use SerendipityHQ\Bundle\UsersBundle\Manager\UsersManagerRegistry;
use SerendipityHQ\Bundle\UsersBundle\Property\HasPlainPasswordInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * {@inheritdoc}
 */
final class SHQUsersExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @var string
     */
    private const PROVIDERS = 'providers';
    /**
     * @var string
     */
    private const ENTITY = 'entity';
    /**
     * @var string
     */
    private const PROPERTY = 'property';
    /**
     * @param ContainerBuilder $containerBuilder
     */
    public function prepend(ContainerBuilder $containerBuilder): void
    {
        $securityExtConfig       = $containerBuilder->getExtensionConfig('security');
        $securityEntityProviders = $securityExtConfig[0][self::PROVIDERS];

        $providers = [];
        foreach ($securityEntityProviders as $provider => $config) {
            $providers[$provider] = [
                'class'    => $config[self::ENTITY]['class'],
                self::PROPERTY => $config[self::ENTITY][self::PROPERTY],
            ];
        }

        $containerBuilder->prependExtensionConfig('shq_users', [self::PROVIDERS => $providers]);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $containerBuilder): void
    {
        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($containerBuilder, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $managerRegistryDefinition = new Definition(UsersManagerRegistry::class);
        $containerBuilder->setDefinition(UsersManagerRegistry::class, $managerRegistryDefinition);

        $dispatcher       = new Reference('event_dispatcher');
        $entityManager    = new Reference('doctrine.orm.default_entity_manager');
        $propertyAccessor = new Reference('property_accessor');
        $encoderFactory   = new Reference('security.encoder_factory');

        foreach ($config[self::PROVIDERS] as $provider => $providerConfig) {
            $manager           = 'shq_users.managers.' . $provider;
            $managerDefinition = new Definition(UsersManager::class, [$provider, $providerConfig['class'], $providerConfig[self::PROPERTY], $dispatcher, $entityManager, $propertyAccessor]);
            $containerBuilder->setDefinition($manager, $managerRegistryDefinition);
            $managerRegistryDefinition->addMethodCall('addManager', [$provider, $managerDefinition]);

            if (\is_subclass_of($providerConfig['class'], HasPlainPasswordInterface::class)) {
                $userEncodePasswordListenerDefinition = (new Definition(UserEncodePasswordListener::class, [$encoderFactory]))
                    ->addTag(
                        'doctrine.orm.entity_listener',
                        [
                            'event'  => Events::preFlush,
                            self::ENTITY => $providerConfig['class'],
                            'lazy'   => true,
                        ]
                    );
                $containerBuilder->setDefinition(UserEncodePasswordListener::class, $userEncodePasswordListenerDefinition);
            }
        }
    }
}
