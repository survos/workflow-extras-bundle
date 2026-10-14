<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;
use Survos\WorkflowExtrasBundle\Contract\WorkflowContinuationInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Contracts\Service\ResetInterface;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postFlush)]
final class WorkflowKickoffListener implements ResetInterface
{
    /** @var array<int, array<string, array{WorkflowContinuationInterface, object, array, bool}>> */
    private array $pending = [];
    private bool $dispatching = false;

    /** @param iterable<WorkflowContinuationInterface> $continuations */
    public function __construct(private readonly iterable $continuations)
    {
    }

    public function postPersist(PostPersistEventArgs $event): void
    {
        $subject = $event->getObject();
        foreach ($this->continuations as $adapter) {
            if ($adapter->supports($subject)) {
                $key = spl_object_id($adapter).':'.spl_object_id($subject);
                $this->pending[spl_object_id($event->getObjectManager())][$key] ??= [$adapter, $subject, [], true];
            }
        }
    }

    #[AsEventListener(event: 'workflow.completed')]
    public function completed(CompletedEvent $event): void
    {
        // Completion may happen outside a Doctrine listener; defer it until the
        // matching manager flushes. The adapter identifies its supported workflow.
        $subject = $event->getSubject();
        foreach ($this->continuations as $adapter) {
            if ($adapter->supports($subject, $event->getWorkflowName())) {
                $this->completed[spl_object_id($adapter).':'.spl_object_id($subject)] = [$adapter, $subject, $event->getContext(), false];
            }
        }
    }

    private array $completed = [];

    public function postFlush(PostFlushEventArgs $event): void
    {
        $manager = $event->getObjectManager();
        $managerId = spl_object_id($manager);
        foreach ($this->completed as $key => $entry) {
            if ($manager->contains($entry[1])) {
                $this->pending[$managerId][$key] = $entry;
                unset($this->completed[$key]);
            }
        }
        if ($this->dispatching || empty($this->pending[$managerId])) {
            return;
        }
        if ($event->getObjectManager()->getConnection()->isTransactionActive()) {
            throw new \LogicException('Workflow kickoff requires a committed flush; transactional producers need an outbox.');
        }
        $this->dispatching = true;
        try {
            while (!empty($this->pending[$managerId])) {
                $pending = $this->pending[$managerId];
                unset($this->pending[$managerId]);
                foreach ($pending as [$adapter, $subject, $context, $initial]) {
                    if ($initial) {
                        $adapter->kickoff($subject);
                    } else {
                        $adapter->continueAfterFlush($subject, $context);
                    }
                }
            }
        } finally {
            $this->dispatching = false;
        }
    }

    public function reset(): void
    {
        $this->pending = [];
        $this->completed = [];
        $this->dispatching = false;
    }
}
