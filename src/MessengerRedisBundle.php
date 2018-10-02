<?php

namespace Krak\SymfonyMessengerRedis;

use Krak\SymfonyMessengerRedis\DependencyInjection\MessengerReceiversPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class MessengerRedisBundle extends Bundle
{
    public function build(ContainerBuilder $container) {
        $container->addCompilerPass(new MessengerReceiversPass());
    }
}
