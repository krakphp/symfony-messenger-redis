<?php

namespace Krak\SymfonyMessengerRedis;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\Config\FileLocator;

class MessengerRedisBundle extends Bundle
{
    public function getContainerExtension(): ExtensionInterface {
        return new class() extends Extension {
            public function getAlias(): string {
                return 'messenger_redis';
            }

            /** @param mixed[] $configs */
            public function load(array $configs, ContainerBuilder $container): void {
                $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/Resources/config'));
                $loader->load('services.xml');
            }
        };
    }
}
