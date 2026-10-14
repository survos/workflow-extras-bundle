<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Tests;

use PHPUnit\Framework\TestCase;
use Survos\WorkflowExtrasBundle\Service\TransitionGroups;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;

final class TransitionGroupsTest extends TestCase
{
    public function testGroupingPreservesMultiPlacePathsAndDistinctGuards(): void
    {
        $first = new Transition('load', ['a', 'b'], 'done');
        $second = new Transition('load', 'c', 'done');
        $guarded = new Transition('load', 'c', 'done');
        $metadata = new \SplObjectStorage();
        $metadata[$first] = ['description' => 'Load'];
        $metadata[$second] = ['description' => 'Load'];
        $metadata[$guarded] = ['description' => 'Load', 'guard' => 'subject.ready'];
        $workflow = new Workflow(new Definition(['a', 'b', 'c', 'done'], [$first, $second, $guarded], ['a', 'b'], new InMemoryMetadataStore([], [], $metadata)));
        $groups = (new TransitionGroups())->forWorkflow($workflow);
        self::assertCount(2, $groups);
        self::assertSame([
            ['froms' => ['a', 'b'], 'tos' => ['done']],
            ['froms' => ['c'], 'tos' => ['done']],
        ], $groups[0]['paths']);
        self::assertSame($first, $groups[0]['transition']);
        self::assertSame($guarded, $groups[1]['transition']);
    }
}
