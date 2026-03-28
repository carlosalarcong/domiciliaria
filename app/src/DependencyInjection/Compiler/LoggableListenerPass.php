<?php

declare(strict_types=1);

namespace App\DependencyInjection\Compiler;

use App\Entity\Tenant\Log\LogEntry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Configura el LoggableListener de Gedmo para usar nuestra entidad LogEntry
 * con campo data tipo json (en lugar del obsoleto array de Doctrine 4).
 */
class LoggableListenerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('stof_doctrine_extensions.listener.loggable')) {
            return;
        }

        $definition = $container->getDefinition('stof_doctrine_extensions.listener.loggable');
        $definition->addMethodCall('setDefaultLogEntryClass', [LogEntry::class]);
    }
}
