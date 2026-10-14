<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Compiler;

use Survos\WorkflowExtrasBundle\Service\WorkflowCatalog;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class WorkflowCatalogPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $definitions = $services = [];
        foreach ($container->findTaggedServiceIds('workflow') as $id => $tags) {
            foreach ($tags as $tag) {
                $name = $tag['name'];
                $definitions[$name] = [
                    'supports' => $tag['supports'] ?? [],
                    'definition_class' => $tag['definition_class'] ?? null,
                    'type' => $container->getDefinition($id)->hasTag('workflow.state_machine') ? 'state_machine' : 'workflow',
                ];
                $services[$name] = new Reference($id);
            }
        }
        ksort($definitions);
        $container->register(WorkflowCatalog::class, WorkflowCatalog::class)
            ->setArguments([ServiceLocatorTagPass::register($container, $services), $definitions]);
    }
}
