<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Service;

use Survos\WorkflowExtrasBundle\Contract\TransitionExecutorInterface;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

final readonly class NextTransitionRunner
{
    public function __construct(private NextTransitionResolver $resolver, private TransitionExecutorInterface $executor)
    {
    }

    /** @return ?string the transition started, if any; none with `cascade: none` */
    public function continue(WorkflowInterface $workflow, object $subject, array $context = [], ?Transition $previous = null): ?string
    {
        if (($context['cascade'] ?? null) === 'none') {
            return null;
        }

        return $this->start($workflow, $subject, $context, $previous);
    }

    /**
     * Start the first enabled `next` of $previous, else of the current places. Unlike
     * continue(), `cascade` only governs what follows the started transition.
     *
     * @return ?string the transition started, if any
     */
    public function start(WorkflowInterface $workflow, object $subject, array $context = [], ?Transition $previous = null): ?string
    {
        $marking = $workflow->getMarking($subject)->getPlaces();
        $metadata = $workflow->getMetadataStore();
        $next = $previous ? $metadata->getTransitionMetadata($previous)['next'] ?? null : null;
        if (null === $next) {
            $next = [];
            foreach (array_keys($marking) as $place) {
                $candidates = $metadata->getPlaceMetadata($place)['next'] ?? [];
                $next = array_merge($next, is_string($candidates) ? [$candidates] : $candidates);
            }
        }
        $name = $this->resolver->firstEnabled($workflow, $subject, array_values(array_unique(is_string($next) ? [$next] : $next)));
        if (null === $name) {
            return null;
        }
        ksort($marking);
        $step = hash('sha256', serialize([$workflow->getName(), $marking, $name]));
        if (isset($context['workflow_extras.visited'][$step])) {
            throw new \LogicException('A next-transition cycle was detected.');
        }
        $context['workflow_extras.visited'][$step] = true;
        $this->executor->execute($workflow, $subject, $name, $context);

        return $name;
    }
}
