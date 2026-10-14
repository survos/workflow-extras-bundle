<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Compiler;

use Survos\WorkflowExtrasBundle\Service\WorkflowCatalog;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class WorkflowCatalogPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $definitions = $services = [];
        foreach ($container->findTaggedServiceIds('workflow') as $id => $tags) {
            foreach ($tags as $tag) {
                self::requireNativeTag($tag);
                $name = $tag['name'];
                $workflow = $container->getDefinition($id);
                $definitions[$name] = [
                    // Native declarations since symfony/symfony#66926.
                    'supports' => $tag['supports'],
                    'support_strategy' => $tag['support_strategy'],
                    'source_class' => $tag['source_class'],
                    'type' => $workflow->hasTag('workflow.state_machine') ? 'state_machine' : 'workflow',
                    'marking_property' => self::markingProperty($workflow),
                ];
                $services[$name] = new Reference($id);
            }
        }
        ksort($definitions);
        $container->register(WorkflowCatalog::class, WorkflowCatalog::class)
            ->setArguments([ServiceLocatorTagPass::register($container, $services), $definitions]);
    }

    /**
     * The workflow tag carries supports, support_strategy and source_class since
     * symfony/symfony#66926; fail clearly on an older FrameworkBundle.
     */
    public static function requireNativeTag(array $tag): void
    {
        if (!array_key_exists('source_class', $tag) || !array_key_exists('supports', $tag)) {
            throw new \LogicException(sprintf('The "workflow" tag of "%s" has no "source_class": survos/workflow-extras-bundle needs symfony/framework-bundle and symfony/workflow 8.2 with symfony/symfony#66926.', $tag['name'] ?? '?'));
        }
    }

    /** The native method marking store's property; null for a custom marking store service. */
    private static function markingProperty(Definition $workflow): ?string
    {
        $arguments = $workflow->getArguments();
        $store = $arguments['index_1'] ?? $arguments[1] ?? null;
        if (null === $store) {
            return 'marking';
        }
        if ($store instanceof ChildDefinition && 'workflow.marking_store.method' === $store->getParent()) {
            return $store->getArguments()[1] ?? 'marking';
        }

        return null;
    }
}
