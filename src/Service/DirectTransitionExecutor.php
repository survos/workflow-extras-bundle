<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Service;

use Survos\WorkflowExtrasBundle\Contract\TransitionExecutorInterface;
use Symfony\Component\Workflow\WorkflowInterface;

final class DirectTransitionExecutor implements TransitionExecutorInterface
{
    public function requiresSavedSubject(): bool
    {
        return false;
    }

    public function execute(WorkflowInterface $workflow, object $subject, string $transition, array $context = []): void
    {
        $workflow->apply($subject, $transition, $context);
    }
}
