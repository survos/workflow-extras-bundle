<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\EventListener;

use Survos\WorkflowExtrasBundle\Contract\WorkflowContinuationInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;

/** Immediate continuation for extras-only workflows; persistence adapters take precedence. */
final class NextTransitionListener
{
    /** @param iterable<WorkflowContinuationInterface> $continuations */
    public function __construct(private readonly \Survos\WorkflowExtrasBundle\Service\NextTransitionRunner $runner, private readonly \Survos\WorkflowExtrasBundle\Service\WorkflowCatalog $catalog, private readonly \Survos\WorkflowExtrasBundle\Contract\TransitionExecutorInterface $executor, private readonly iterable $continuations = [])
    {
    }

    public function completed(CompletedEvent $event): void
    {
        if ($this->executor->requiresSavedSubject()) {
            return;
        }
        if (($event->getContext()['workflow_extras.defer'] ?? false) || ($event->getContext()['cascade'] ?? null) === 'none') {
            return;
        }
        foreach ($this->continuations as $adapter) {
            if ($adapter->supports($event->getSubject(), $event->getWorkflowName())) {
                return;
            }
        }
        $this->runner->continue($this->catalog->get($event->getWorkflowName()), $event->getSubject(), $event->getContext(), $event->getTransition());
    }
}
