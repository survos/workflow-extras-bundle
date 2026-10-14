<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Workflow\Attribute\AsWorkflow;
use Symfony\Component\Workflow\Attribute\Place;
use Symfony\Component\Workflow\Attribute\Transition;
use Symfony\Component\Workflow\DependencyInjection\AttributeReader;

/** Enrich native metadata after registration; never registers or executes a workflow. */
final class DefaultLabelsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds('.workflow_extras.labels') as $id => $tags) {
            $service = $container->getDefinition($id);
            $service->clearTag('.workflow_extras.labels');
            $class = $container->getReflectionClass($container->getParameterBag()->resolveValue($service->getClass()));
            $attribute = $class->getAttributes(AsWorkflow::class, \ReflectionAttribute::IS_INSTANCEOF)[0]->newInstance();
            // Reuse native name derivation, including unnamed workflows.
            $name = array_key_first((new AttributeReader())->read($attribute, $class));
            // Compatibility with Symfony versions before symfony/symfony#66926.
            $workflowService = $container->getDefinition($attribute->type->value.'.'.$name);
            $workflowTags = $workflowService->getTag('workflow');
            $workflowService->clearTag('workflow');
            foreach ($workflowTags as $tag) {
                $tag += ['supports' => $attribute->supports, 'definition_class' => $class->name];
                $workflowService->addTag('workflow', $tag);
            }

            $store = $container->getDefinition($attribute->type->value.'.'.$name.'.metadata_store');
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
                $definition = $container->getDefinition($attribute->type->value.'.'.$name.'.definition');
                if (in_array($place, $definition->getArgument(0), true)) {
                    $placeMetadata[$place]['label'] ??= $label;
                }
            }
            $store->setArgument(1, $placeMetadata);
            $transitionMetadata = $store->getArgument(2);
            if (!$transitionMetadata instanceof Definition) {
                continue;
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
            $definition = $container->getDefinition($attribute->type->value.'.'.$name.'.definition');
            foreach ($definition->getArgument(1) as $reference) {
                if (!isset($seen[(string) $reference])) {
                    $transitionName = $container->getDefinition((string) $reference)->getArgument(0);
                    $transitionMetadata->addMethodCall('offsetSet', [$reference, ['label' => $transitions[$transitionName] ?? self::humanize($transitionName)]]);
                }
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
