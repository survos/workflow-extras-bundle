<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\EventListener;

use Symfony\Component\Workflow\Event\CompletedEvent;

/** Immediate continuation when transitions execute directly (extras without Messenger). */
final class NextTransitionListener
{
    public function __construct(private readonly \Survos\WorkflowExtrasBundle\Service\NextTransitionRunner $runner, private readonly \Survos\WorkflowExtrasBundle\Service\WorkflowCatalog $catalog, private readonly \Survos\WorkflowExtrasBundle\Contract\TransitionExecutorInterface $executor)
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
        $this->runner->continue($this->catalog->get($event->getWorkflowName()), $event->getSubject(), $event->getContext(), $event->getTransition());
    }
}
