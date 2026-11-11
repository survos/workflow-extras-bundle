<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Service;

use Survos\WorkflowExtrasBundle\Contract\TransitionExecutorInterface;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

final readonly class NextTransitionRunner
{
    /** Context key counting the transitions one continuation has chained so far. */
    public const string HOPS = 'workflow_extras.hops';
    /** A chain this long is a runaway loop, not a pipeline; a legitimate repeat (e.g. a retry self-loop) stays well under it. */
    public const int MAX_HOPS = 50;

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
        $hops = ($context[self::HOPS] ?? 0) + 1;
        if ($hops > self::MAX_HOPS) {
            throw new \LogicException(sprintf('A next-transition cycle was detected: %d transitions were chained in "%s" without stopping.', self::MAX_HOPS, $workflow->getName()));
        }
        $context[self::HOPS] = $hops;
        $this->executor->execute($workflow, $subject, $name, $context);

        return $name;
    }
}
