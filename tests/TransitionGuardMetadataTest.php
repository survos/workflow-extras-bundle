<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Tests;

use PHPUnit\Framework\TestCase;
use Survos\WorkflowExtrasBundle\Attribute\Transition;

final class TransitionGuardMetadataTest extends TestCase
{
    public function testDisplayedGuardMatchesExecutionGuard(): void
    {
        $transition = new Transition('loaded', 'accepted', guard: 'subject.ready', metadata: ['guard' => 'stale expression']);
        self::assertSame($transition->guard, $transition->metadata['guard']);
    }
}
