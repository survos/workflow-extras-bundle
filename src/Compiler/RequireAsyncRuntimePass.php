<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

/** Inspect definitions produced by Symfony, including attribute and YAML workflows. */
final class RequireAsyncRuntimePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // A class existing in vendor is insufficient: the runtime must be enabled.
        if ($container->hasDefinition('Survos\\WorkflowAsyncBundle\\Service\\TransitionHandler')) {
            return;
        }
        foreach ($container->findTaggedServiceIds('workflow') as $tags) {
            foreach ($tags as $tag) {
                if (!isset($tag['definition_id']) || !$container->hasDefinition($tag['definition_id'])) {
                    continue;
                }
                $definition = $container->getDefinition($tag['definition_id']);
                $store = $definition->getArgument(3);
                if (!$store instanceof Reference || !$container->hasDefinition((string) $store)) {
                    continue;
                }
                $transitions = $container->getDefinition((string) $store)->getArgument(2);
                if (!$transitions instanceof Definition) {
                    continue;
                }
                foreach ($transitions->getMethodCalls() as [$method, $arguments]) {
                    if ($method !== 'offsetSet' || ($arguments[1]['async'] ?? false) !== true) {
                        continue;
                    }
                    $transition = $container->getDefinition((string) $arguments[0])->getArgument(0);
                    throw new LogicException(sprintf(
                        'Workflow "%s" transition "%s" is marked async: true. Install survos/workflow-async-bundle and enable SurvosWorkflowAsyncBundle; configure its subject_store and a consumable Symfony Messenger transport. Extras alone does not execute async transitions.',
                        $tag['name'], $transition,
                    ));
                }
            }
        }
    }
}
