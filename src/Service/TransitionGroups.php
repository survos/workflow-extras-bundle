<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Service;

use Symfony\Component\Workflow\WorkflowInterface;

/** Group display rows without changing transition objects or merging multi-place paths. */
final class TransitionGroups
{
    public function forWorkflow(WorkflowInterface $workflow): array
    {
        $groups = [];
        foreach ($workflow->getDefinition()->getTransitions() as $transition) {
            $metadata = $workflow->getMetadataStore()->getTransitionMetadata($transition);
            $key = serialize([$transition->getName(), $metadata]);
            $groups[$key] ??= ['transition' => $transition, 'paths' => []];
            $groups[$key]['paths'][] = ['froms' => $transition->getFroms(), 'tos' => $transition->getTos()];
        }

        return array_values($groups);
    }
}
