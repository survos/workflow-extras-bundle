<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Tests;

use PHPUnit\Framework\TestCase;
use Survos\WorkflowExtrasBundle\Attribute\AsWorkflow;
use Survos\WorkflowExtrasBundle\Attribute\Place;
use Survos\WorkflowExtrasBundle\Attribute\Transition;
use Survos\WorkflowExtrasBundle\Service\NextTransitionResolver;
use Symfony\Component\Workflow\Attribute\AsWorkflow as NativeWorkflow;
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

    public function testConvenienceArgumentOrder(): void
    {
        $place = new Place('Discovered', 'New', 'fetch', true, ['color' => 'blue']);
        self::assertTrue($place->initial);
        self::assertSame(['color' => 'blue', 'next' => ['fetch'], 'label' => 'New', 'description' => 'Discovered'], $place->metadata);
        $transition = new Transition('a', 'b', 'Fetch', 'Load', 'validate', 'subject.ready', ['color' => 'blue']);
        self::assertSame('subject.ready', $transition->guard);
        self::assertSame(['validate'], $transition->metadata['next']);
        self::assertSame('Load', $transition->metadata['label']);
    }

    public function testNextNormalizesWithoutLosingOrderOrEmptyList(): void
    {
        self::assertArrayNotHasKey('next', (new Place())->metadata);
        self::assertSame([], (new Place(next: []))->metadata['next']);
        self::assertSame(['fetch'], (new Place(next: 'fetch'))->metadata['next']);
        self::assertSame(['skip', 'fetch'], (new Transition('a', 'b', next: ['skip', 'fetch']))->metadata['next']);
        self::assertSame(['fetch'], (new Place(next: 'fetch', metadata: ['next' => ['fetch']]))->metadata['next']);
        self::assertSame(['fetch'], (new Place(metadata: ['next' => 'fetch']))->metadata['next']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidMetadata')]
    public function testInvalidOrConflictingMetadataIsRejected(array $arguments): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Place(...$arguments);
    }

    public static function invalidMetadata(): iterable
    {
        yield [['next' => '']];
        yield [['next' => [' ']]];
        yield [['next' => ['first' => 'fetch']]];
        yield [['next' => [42]]];
        yield [['next' => 'fetch', 'metadata' => ['next' => ['skip']]]];
        yield [['label' => 'New', 'metadata' => ['label' => 'Old']]];
        yield [['metadata' => ['next' => null]]];
    }

    public function testExtrasDoesNotDeclareAsyncOptions(): void
    {
        $parameters = array_map(static fn ($p) => $p->getName(), (new \ReflectionMethod(Transition::class, '__construct'))->getParameters());
        self::assertNotContains('async', $parameters);
        self::assertNotContains('transport', $parameters);
        self::assertArrayNotHasKey('async', (new Transition('a', 'b'))->metadata);
    }

    public function testNativeReaderLoadsEnumPlaceMetadataAndEndpoints(): void
    {
        $class = new \ReflectionClass(EnumDefinition::class);
        $attribute = $class->getAttributes(AsWorkflow::class)[0]->newInstance();
        $config = (new \Symfony\Component\Workflow\DependencyInjection\AttributeReader())->read($attribute, $class)['enum_test'];
        self::assertSame(['new'], $config['initial_marking']);
        self::assertSame(['new', 'done'], array_column($config['places'], 'name'));
        self::assertSame(['next' => ['finish'], 'label' => 'New', 'description' => 'Discovered'], $config['places'][0]['metadata']);
        self::assertSame([['place' => 'new']], $config['transitions'][0]['from']);
        self::assertSame([['place' => 'done']], $config['transitions'][0]['to']);
        self::assertSame('Finish', $config['transitions'][0]['metadata']['label']);
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

enum EnumPlace: string
{
    #[Place('Discovered', 'New', 'finish', true)]
    case New = 'new';
    #[Place('Completed', 'Done')]
    case Done = 'done';
}

#[AsWorkflow(name: 'enum_test', places: EnumPlace::class)]
final class EnumDefinition
{
    #[Transition(EnumPlace::New, EnumPlace::Done, label: 'Finish')]
    public const FINISH = 'finish';
}
