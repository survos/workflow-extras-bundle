<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Tests;

use PHPUnit\Framework\TestCase;
use Survos\WorkflowExtrasBundle\Attribute\AsWorkflow;
use Survos\WorkflowExtrasBundle\Attribute\Place;
use Survos\WorkflowExtrasBundle\Attribute\Transition;
use Survos\WorkflowExtrasBundle\Service\NextTransitionResolver;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Transition as NativeTransition;

final class AttributesTest extends TestCase
{
    public function testDescriptionsAndNextAreMetadataWhileInitialRemainsNative(): void
    {
        $place = new Place('Discovered', initial: true, metadata: ['description' => 'old', 'label' => 'New'], next: ['fetch', 'skip']);
        self::assertTrue($place->initial);
        self::assertSame(['description' => 'Discovered', 'label' => 'New', 'next' => ['fetch', 'skip']], $place->metadata);
        self::assertSame(['description' => 'Process packages'], (new AsWorkflow(description: 'Process packages'))->metadata);
        $transition = new Transition('a', 'b', 'Fetch', metadata: ['async' => true, 'transport' => 'downloads']);
        self::assertTrue($transition->metadata['async']);
        self::assertSame('Fetch', $transition->metadata['description']);
    }

    public function testTypedAsyncOptionsRemainOptionalMetadata(): void
    {
        $native = new Transition('a', 'b');
        self::assertArrayNotHasKey('async', $native->metadata);
        $async = new Transition('a', 'b', 'Fetch', async: true, transport: 'downloads');
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
        $subject = new class { public string $marking = 'a'; };
        $resolver = new NextTransitionResolver();
        self::assertSame('second', $resolver->firstEnabled($workflow, $subject, ['second', 'first']));
        self::assertSame('a', $subject->marking);
        $subject->marking = 'b';
        self::assertNull($resolver->firstEnabled($workflow, $subject, ['first']));
    }

    public function testUnknownNextIsRejectedEvenAfterAnEnabledCandidate(): void
    {
        $workflow = new StateMachine(new Definition(['a', 'b'], [new NativeTransition('go', 'a', 'b')], 'a'));
        $subject = new class { public string $marking = 'a'; };
        $this->expectException(\InvalidArgumentException::class);
        (new NextTransitionResolver())->firstEnabled($workflow, $subject, ['go', 'typo']);
    }
}
