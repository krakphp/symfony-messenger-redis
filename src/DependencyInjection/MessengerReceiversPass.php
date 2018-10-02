<?php

namespace Krak\SymfonyMessengerRedis\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/** Adds a parameter for the registered receiver names to the container */
class MessengerReceiversPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container) {
        $receiverMapping = [];
        foreach ($container->findTaggedServiceIds('messenger.receiver') as $id => $tags) {
            foreach ($tags as $tag) {
                if (isset($tag['alias'])) {
                    $receiverMapping[$tag['alias']] = null;
                }
            }
        }

        $container->setParameter('messenger.receiver_names', \array_unique(\array_keys($receiverMapping)));
    }
}
