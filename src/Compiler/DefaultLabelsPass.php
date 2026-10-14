<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Workflow\Attribute\AsWorkflow;
use Symfony\Component\Workflow\Attribute\Place;
use Symfony\Component\Workflow\Attribute\Transition;

/** Enrich native metadata after registration; never registers or executes a workflow. */
final class DefaultLabelsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds('workflow') as $tags) {
            foreach ($tags as $tag) {
                // Native since symfony/symfony#66926; null for configuration-defined workflows.
                if (null !== $tag['source_class']) {
                    self::label($container, $tag['source_class'], $container->getDefinition($tag['definition_id']));
                }
            }
        }
    }

    private static function label(ContainerBuilder $container, string $sourceClass, Definition $definition): void
    {
        $class = $container->getReflectionClass($sourceClass);
        $attribute = $class->getAttributes(AsWorkflow::class, \ReflectionAttribute::IS_INSTANCEOF)[0]->newInstance();
        $store = $container->getDefinition((string) $definition->getArgument(3));
        $places = [];
        $transitions = [];
        $enums = null === $attribute->places ? [] : [$attribute->places => true];
        foreach ($class->getReflectionConstants() as $constant) {
            $value = $constant->getValue();
            foreach ($constant->getAttributes(Transition::class, \ReflectionAttribute::IS_INSTANCEOF) as $reflection) {
                $transitions[$value] = self::humanize($constant->name);
                $transition = $reflection->newInstance();
                foreach ([$transition->from, $transition->to] as $endpoints) {
                    foreach (is_array($endpoints) ? $endpoints : [$endpoints] as $endpoint) {
                        if ($endpoint instanceof \BackedEnum) {
                            $enums[$endpoint::class] = true;
                        }
                    }
                }
            }
            if ($constant->getAttributes(Place::class, \ReflectionAttribute::IS_INSTANCEOF)) {
                if ($value instanceof \BackedEnum) {
                    $enums[$value::class] = true;
                } else {
                    $places[$value] = self::humanize($constant->name);
                }
            }
        }
        foreach (array_keys($enums) as $enum) {
            foreach ($enum::cases() as $case) {
                $places[$case->value] ??= self::humanize($case->name);
            }
        }
        $placeMetadata = $store->getArgument(1);
        foreach ($places as $place => $label) {
            // Only enrich places actually present in the compiled definition.
            if (in_array($place, $definition->getArgument(0), true)) {
                $placeMetadata[$place]['label'] ??= $label;
            }
        }
        $store->setArgument(1, $placeMetadata);
        $transitionMetadata = $store->getArgument(2);
        if (!$transitionMetadata instanceof Definition) {
            return;
        }
        $calls = $transitionMetadata->getMethodCalls();
        $seen = [];
        foreach ($calls as &$call) {
            if ('offsetSet' !== $call[0]) {
                continue;
            }
            $transitionId = (string) $call[1][0];
            $transitionName = $container->getDefinition($transitionId)->getArgument(0);
            $call[1][1]['label'] ??= $transitions[$transitionName] ?? self::humanize($transitionName);
            $seen[$transitionId] = true;
        }
        unset($call);
        $transitionMetadata->setMethodCalls($calls);
        foreach ($definition->getArgument(1) as $reference) {
            if (!isset($seen[(string) $reference])) {
                $transitionName = $container->getDefinition((string) $reference)->getArgument(0);
                $transitionMetadata->addMethodCall('offsetSet', [$reference, ['label' => $transitions[$transitionName] ?? self::humanize($transitionName)]]);
            }
        }
    }

    private static function humanize(string $name): string
    {
        $name = preg_replace('/^(?:PLACE|TRANSITION)_/', '', $name);
        $name = preg_replace(['/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'], '$1 $2', $name);
        $name = str_replace(['_', '-'], ' ', $name);

        return ucfirst(strtolower($name));
    }
}
