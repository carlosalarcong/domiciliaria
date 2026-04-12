<?php

declare(strict_types=1);

namespace App\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Evita que Symfony cargue en el catálogo global los archivos YAML
 * de traducción que están en subdirectorios de translations/.
 *
 * Symfony escanea translations/ recursivamente, por lo que sin este pass
 * un archivo como translations/demo/messages.es.yaml quedaría disponible
 * para TODOS los tenants. Este pass lo elimina del translator.default
 * y deja que TenantAwareTranslator lo cargue sólo cuando corresponde.
 */
final class ExcludeTenantTranslationResourcesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('translator.default')) {
            return;
        }

        /** @var string $translationsDir */
        $translationsDir = rtrim((string) $container->getParameter('kernel.project_dir'), '/')
            . '/translations/';

        $definition = $container->findDefinition('translator.default');
        $filtered   = [];

        foreach ($definition->getMethodCalls() as [$method, $args]) {
            if ($method === 'addResource' && isset($args[1]) && is_string($args[1])) {
                $relative = substr($args[1], strlen($translationsDir));
                // Excluir si el archivo está dentro de un subdirectorio (slug del tenant)
                if (str_contains($relative, '/')) {
                    continue;
                }
            }
            $filtered[] = [$method, $args];
        }

        $definition->setMethodCalls($filtered);
    }
}
