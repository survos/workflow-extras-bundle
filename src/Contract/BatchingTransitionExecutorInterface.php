<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Contract;

/**
 * Optional capability of an executor that queues transitions: send what a callback
 * starts in one batch instead of one transport request per transition.
 */
interface BatchingTransitionExecutorInterface
{
    /**
     * Run $callback, then send every transition it queued in as few transport requests
     * as possible. When $callback throws, none of them is sent.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function batched(callable $callback): mixed;
}
