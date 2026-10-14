<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Contract;

use Symfony\Component\Workflow\WorkflowInterface;

interface TransitionExecutorInterface
{
    public function requiresSavedSubject(): bool;

    public function execute(WorkflowInterface $workflow, object $subject, string $transition, array $context = []): void;

    /**
     * Run $callback with every transition it starts, kickoffs included, handled in
     * this process (a CLI --sync run). Direct execution always is.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function synchronously(callable $callback): mixed;
}
