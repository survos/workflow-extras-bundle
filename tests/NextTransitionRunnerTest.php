<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Tests;

use PHPUnit\Framework\TestCase;
use Survos\WorkflowExtrasBundle\Contract\TransitionExecutorInterface;
use Survos\WorkflowExtrasBundle\Service\NextTransitionResolver;
use Survos\WorkflowExtrasBundle\Service\NextTransitionRunner;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

final class NextTransitionRunnerTest extends TestCase
{
    public function testARepeatingSelfLoopThatStopsIsNotACycle(): void
    {
        $subject = new class {
            public string $marking = 'working';
            public int $runs = 0;
        };
        $this->runLoop($subject, stopAfter: 7);

        self::assertSame(7, $subject->runs);
    }

    public function testARunawayLoopIsStopped(): void
    {
        $subject = new class {
            public string $marking = 'working';
            public int $runs = 0;
        };
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cycle');
        try {
            $this->runLoop($subject, stopAfter: PHP_INT_MAX);
        } finally {
            self::assertSame(NextTransitionRunner::MAX_HOPS + 1, $subject->runs, 'the first run plus the allowed hops');
        }
    }

    private function runLoop(object $subject, int $stopAfter): void
    {
        $loop = new Transition('again', 'working', 'working');
        $metadata = new \SplObjectStorage();
        $metadata[$loop] = ['next' => ['again']];
        $definition = new Definition(['working'], [$loop], 'working', new InMemoryMetadataStore(transitionsMetadata: $metadata));
        $events = new EventDispatcher();
        $events->addListener('workflow.guard', static function (GuardEvent $event) use ($stopAfter): void {
            if ($event->getSubject()->runs >= $stopAfter) {
                $event->setBlocked(true);
            }
        });
        $workflow = new StateMachine($definition, new MethodMarkingStore(true, 'marking'), $events, 'loop');

        $runner = null;
        $executor = new class($workflow, $runner) implements TransitionExecutorInterface {
            public ?NextTransitionRunner $runner = null;

            public function __construct(private readonly WorkflowInterface $workflow, mixed &$unused)
            {
            }

            public function requiresSavedSubject(): bool
            {
                return false;
            }

            public function execute(WorkflowInterface $workflow, object $subject, string $transition, array $context = []): void
            {
                $workflow->apply($subject, $transition, $context);
                ++$subject->runs;
                $this->runner->continue($workflow, $subject, $context, $this->findTransition($workflow, $transition));
            }

            public function synchronously(callable $callback): mixed
            {
                return $callback();
            }

            private function findTransition(WorkflowInterface $workflow, string $name): Transition
            {
                foreach ($workflow->getDefinition()->getTransitions() as $transition) {
                    if ($transition->getName() === $name) {
                        return $transition;
                    }
                }
                throw new \LogicException($name);
            }
        };
        $executor->runner = $runner = new NextTransitionRunner(new NextTransitionResolver(), $executor);
        $executor->execute($workflow, $subject, 'again');
    }
}
