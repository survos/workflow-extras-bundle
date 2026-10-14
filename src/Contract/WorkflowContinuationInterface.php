<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Contract;

/** Opt-in persistence adapter; implementation owns applying/dispatching and initial marking policy. */
interface WorkflowContinuationInterface
{
    public function supports(object $subject, ?string $workflowName = null): bool;

    public function kickoff(object $subject): void;

    public function continueAfterFlush(object $subject, array $context = []): void;
}
