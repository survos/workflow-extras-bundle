<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Tests;

use PHPUnit\Framework\TestCase;
use Survos\WorkflowExtrasBundle\Attribute\AsWorkflow;
use Survos\WorkflowExtrasBundle\Attribute\Place;
use Survos\WorkflowExtrasBundle\Attribute\Transition;
use Survos\WorkflowExtrasBundle\Service\NextTransitionResolver;
use Symfony\Component\Workflow\Attribute\AsWorkflow as NativeWorkflow;
use Symfony\Component\Workflow\Attribute\Place as NativePlace;
use Symfony\Component\Workflow\Attribute\Transition as NativeTransitionAttribute;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Transition as NativeTransition;

final class AttributesTest extends TestCase
{
    public function testDescriptionsAreNativeAndNextIsMetadata(): void
    {
        $place = new Place('Discovered', initial: true, metadata: ['label' => 'New'], next: ['fetch', 'skip']);
        self::assertTrue($place->initial);
        self::assertSame(['label' => 'New', 'next' => ['fetch', 'skip'], 'description' => 'Discovered'], $place->metadata);
        $transition = new Transition('a', 'b', description: 'Fetch', metadata: ['async' => true, 'transport' => 'downloads']);
        self::assertTrue($transition->metadata['async']);
        self::assertSame('Fetch', $transition->metadata['description']);
    }

    public function testPositionsMatchNative(): void
    {
        // Leading positional arguments are the native ones, so swapping an import is safe.
        $extras = new Place('Discovered', ['label' => 'New'], true);
        $native = new NativePlace('Discovered', ['label' => 'New'], true);
        self::assertSame([$native->metadata, $native->initial], [$extras->metadata, $extras->initial]);

        $extras = new Transition('a', 'b', 'Fetch', 'subject.ready', ['label' => 'Load']);
        $native = new NativeTransitionAttribute('a', 'b', 'Fetch', 'subject.ready', ['label' => 'Load']);
        self::assertSame([$native->guard, $native->metadata], [$extras->guard, $extras->metadata]);
    }

    public function testAsWorkflowIsTheNativeAttribute(): void
    {
        $workflow = new AsWorkflow(supports: \stdClass::class, description: 'Process packages');
        self::assertInstanceOf(NativeWorkflow::class, $workflow);
        self::assertSame(['description' => 'Process packages'], $workflow->metadata);
    }

    public function testDescriptionArgumentAndMetadataKeyConflictAsInCore(): void
    {
        $this->expectException(LogicException::class);
        new Place('Discovered', metadata: ['description' => 'old']);
    }

    public function testTypedAsyncOptionsRemainOptionalMetadata(): void
    {
        $native = new Transition('a', 'b');
        self::assertArrayNotHasKey('async', $native->metadata);
        $async = new Transition('a', 'b', description: 'Fetch', async: true, transport: 'downloads');
        self::assertSame(['async' => true, 'transport' => 'downloads', 'description' => 'Fetch'], $async->metadata);
        self::assertSame($async->metadata, (new Transition('a', 'b', metadata: $async->metadata))->metadata);
    }

    public function testTransportCannotSilentlyEnableAsync(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Transition('a', 'b', transport: 'downloads');
    }

    public function testConflictingAsyncMetadataIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Transition('a', 'b', metadata: ['async' => false], async: true);
    }

    public function testNullDescriptionPreservesNativeMetadata(): void
    {
        self::assertSame('Existing', (new Place(metadata: ['description' => 'Existing']))->metadata['description']);
    }

    public function testNextResolverPreservesOrderAndDoesNotApply(): void
    {
        $workflow = new StateMachine(new Definition(['a', 'b'], [new NativeTransition('first', 'a', 'b'), new NativeTransition('second', 'a', 'b')], 'a'));
        $subject = new class {
            public string $marking = 'a';
        };
        $resolver = new NextTransitionResolver();
        self::assertSame('second', $resolver->firstEnabled($workflow, $subject, ['second', 'first']));
        self::assertSame('a', $subject->marking);
        $subject->marking = 'b';
        self::assertNull($resolver->firstEnabled($workflow, $subject, ['first']));
    }

    public function testUnknownNextIsRejectedEvenAfterAnEnabledCandidate(): void
    {
        $workflow = new StateMachine(new Definition(['a', 'b'], [new NativeTransition('go', 'a', 'b')], 'a'));
        $subject = new class {
            public string $marking = 'a';
        };
        $this->expectException(\InvalidArgumentException::class);
        (new NextTransitionResolver())->firstEnabled($workflow, $subject, ['go', 'typo']);
    }
}
