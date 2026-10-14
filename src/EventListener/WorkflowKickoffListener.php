<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Survos\WorkflowExtrasBundle\Contract\TransitionExecutorInterface;
use Survos\WorkflowExtrasBundle\Service\NextTransitionRunner;
use Survos\WorkflowExtrasBundle\Service\WorkflowCatalog;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Doctrine initial marking, kickoff and `next` continuation:
 *
 *  - an entity persisted with an empty (null or uninitialized) marking is given its
 *    workflow's initial place(s) before insert, so constructors need not set it;
 *  - an entity persisted in its workflow's initial place runs the first enabled
 *    `next` of that place (`#[Place(initial: true, next: ...)]`);
 *  - with an executor that needs saved subjects (Messenger), a transition applied
 *    directly by application code continues once its entity is flushed.
 *
 * Workflows are found through the catalog's declared `supports`. There is no outbox:
 * continuing inside an open transaction is rejected.
 */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postFlush)]
final class WorkflowKickoffListener implements ResetInterface
{
    /** @var array<int, array<string, array{WorkflowInterface, object, array, ?Transition, bool}>> manager => entries */
    private array $pending = [];
    /** @var array<string, array{WorkflowInterface, object, array, ?Transition, bool}> */
    private array $completed = [];
    private bool $dispatching = false;

    public function __construct(
        private readonly WorkflowCatalog $catalog,
        private readonly NextTransitionRunner $runner,
        private readonly TransitionExecutorInterface $executor,
    ) {
    }

    public function prePersist(PrePersistEventArgs $event): void
    {
        $subject = $event->getObject();
        foreach ($this->catalog->forClass($subject::class) as $name) {
            // Native getMarking() stores the initial marking when the marking is empty.
            $this->catalog->get($name)->getMarking($subject);
        }
    }

    public function postPersist(PostPersistEventArgs $event): void
    {
        $subject = $event->getObject();
        foreach ($this->catalog->forClass($subject::class) as $name) {
            $this->pending[spl_object_id($event->getObjectManager())][$name.':'.spl_object_id($subject)] ??= [$this->catalog->get($name), $subject, [], null, true];
        }
    }

    #[AsEventListener(event: 'workflow.completed')]
    public function completed(CompletedEvent $event): void
    {
        // Direct execution continues immediately (NextTransitionListener); the
        // Messenger handler continues its own requests after saving them.
        if (!$this->executor->requiresSavedSubject() || ($event->getContext()['workflow_extras.defer'] ?? false) || ($event->getContext()['cascade'] ?? null) === 'none') {
            return;
        }
        $subject = $event->getSubject();
        $this->completed[$event->getWorkflowName().':'.spl_object_id($subject)] = [$this->catalog->get($event->getWorkflowName()), $subject, $event->getContext(), $event->getTransition(), false];
    }

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
        if ($manager->getConnection()->isTransactionActive()) {
            throw new \LogicException('Workflow kickoff requires a committed flush; transactional producers need an outbox.');
        }
        $this->dispatching = true;
        try {
            while (!empty($this->pending[$managerId])) {
                $pending = $this->pending[$managerId];
                unset($this->pending[$managerId]);
                foreach ($pending as [$workflow, $subject, $context, $previous, $initial]) {
                    if ($initial && !self::isInInitialPlace($workflow, $subject)) {
                        // Imported mid-pipeline or re-persisted: already started.
                        continue;
                    }
                    $this->runner->continue($workflow, $subject, $context, $previous);
                }
                if (!$this->executor->requiresSavedSubject()) {
                    // Direct execution changed state in memory; save it. The nested
                    // postFlush only queues; this loop drains it.
                    $manager->flush();
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

    private static function isInInitialPlace(WorkflowInterface $workflow, object $subject): bool
    {
        $places = array_map('strval', array_keys($workflow->getMarking($subject)->getPlaces()));
        $initial = $workflow->getDefinition()->getInitialPlaces();
        sort($places);
        sort($initial);

        return $places === $initial;
    }
}
