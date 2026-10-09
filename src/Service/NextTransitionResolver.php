<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Service;

use Symfony\Component\Workflow\WorkflowInterface;

/** Selection only. The caller decides when to apply or enqueue, after persistence. */
final class NextTransitionResolver
{
    /** @param list<string> $next Ordered transition names, e.g. place metadata['next']. */
    public function firstEnabled(WorkflowInterface $workflow, object $subject, array $next): ?string
    {
        if (!array_is_list($next)) {
            throw new \InvalidArgumentException('Next transitions must be an ordered list.');
        }
        $known = array_map(static fn ($transition) => $transition->getName(), $workflow->getDefinition()->getTransitions());
        foreach ($next as $name) {
            if (!is_string($name) || !in_array($name, $known, true)) {
                throw new \InvalidArgumentException('Unknown next transition; check the workflow metadata.');
            }
        }
        foreach ($next as $name) {
            if ($workflow->can($subject, $name)) {
                return $name;
            }
        }

        return null;
    }
}
