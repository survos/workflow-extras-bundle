<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Contract;

use Symfony\Component\Workflow\WorkflowInterface;

interface TransitionExecutorInterface
{
    public function requiresSavedSubject(): bool;

    public function execute(WorkflowInterface $workflow, object $subject, string $transition, array $context = []): void;
}
