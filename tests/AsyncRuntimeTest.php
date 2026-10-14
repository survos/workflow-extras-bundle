<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Tests;

use PHPUnit\Framework\TestCase;
use Survos\WorkflowExtrasBundle\Attribute\AsWorkflow;
use Survos\WorkflowExtrasBundle\Attribute\Place;
use Survos\WorkflowExtrasBundle\Attribute\Transition;
use Survos\WorkflowExtrasBundle\SurvosWorkflowExtrasBundle;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

final class AsyncRuntimeTest extends TestCase
{
    public function testMarkedAsyncRequiresEnabledRuntimeEvenWhenItsClassesAreInstalled(): void
    {
        $kernel = new ExtrasOnlyKernel(AsyncDefinition::class);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Install survos/workflow-async-bundle');
        $kernel->boot();
    }

    public function testDefaultLabelsUseDeclarationNamesAndRespectOverrides(): void
    {
        $kernel = new ExtrasOnlyKernel(LabelDefinition::class);
        try {
            $kernel->boot();
            $store = $kernel->getContainer()->get('test.workflow')->getMetadataStore();
            self::assertSame('Discovered', $store->getPlaceMetadata('new')['label']);
            self::assertSame('Custom finished label', $store->getPlaceMetadata('done')['label']);
            self::assertSame('Waiting review', $store->getPlaceMetadata('review')['label']);
            $transitions = $kernel->getContainer()->get('test.workflow')->getDefinition()->getTransitions();
            self::assertSame('Finish processing', $store->getTransitionMetadata($transitions[0])['label']);
            self::assertSame('Custom retry', $store->getTransitionMetadata($transitions[1])['label']);
            self::assertSame('Finish processing', $store->getTransitionMetadata($transitions[2])['label']);
        } finally {
            $kernel->shutdown();
        }
    }

    public function testSynchronousNextNeedsNoMessengerBundle(): void
    {
        $kernel = new ExtrasOnlyKernel(SyncDefinition::class);
        try {
            $kernel->boot();
            self::assertArrayNotHasKey('MessengerBundle', $kernel->getBundles());
            $workflow = $kernel->getContainer()->get('test.workflow');
            $subject = new class {
                public string $marking = SyncDefinition::NEW;
            };
            $workflow->apply($subject, SyncDefinition::FINISH);
            self::assertSame(SyncDefinition::DONE, $subject->marking);
        } finally {
            $kernel->shutdown();
        }
    }
}

final class ExtrasOnlyKernel extends AbstractKernel
{
    use KernelTrait;

    private string $directory;

    public function __construct(private string $definition)
    {
        parent::__construct('test', true);
        $this->directory = sys_get_temp_dir().'/workflow-extras-test-'.bin2hex(random_bytes(6));
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__);
    }

    public function getCacheDir(): string
    {
        return $this->directory.'/cache';
    }

    public function getLogDir(): string
    {
        return $this->directory.'/log';
    }

    public function registerBundles(): iterable
    {
        yield new SurvosWorkflowExtrasBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('workflow', []);
        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set($this->definition);
        $services->alias('test.workflow', 'state_machine.test')->public();
    }
}

#[AsWorkflow(name: 'test')]
final class AsyncDefinition
{
    #[Place('New', initial: true)]
    public const NEW = 'new';
    #[Place('Done')]
    public const DONE = 'done';
    #[Transition(self::NEW, self::DONE, 'Finish asynchronously', metadata: ['async' => true, 'transport' => 'jobs'])]
    public const FINISH = 'finish';
}

#[AsWorkflow(name: 'test')]
final class SyncDefinition
{
    #[Place('New', initial: true, next: [self::FINISH])]
    public const NEW = 'new';
    #[Place('Done')]
    public const DONE = 'done';
    #[Transition(self::NEW, self::DONE, 'Finish synchronously')]
    public const FINISH = 'finish';
}

enum LabelPlace: string
{
    #[Place(initial: true)]
    case Discovered = 'new';
    #[Place(label: 'Custom finished label')]
    case Finished = 'done';
}

#[AsWorkflow(name: 'test')]
final class LabelDefinition
{
    #[Place]
    public const WAITING_REVIEW = 'review';

    #[Transition(LabelPlace::Discovered, LabelPlace::Finished)]
    public const FINISH_PROCESSING = 'finish';

    #[Transition(LabelPlace::Finished, self::WAITING_REVIEW, label: 'Custom retry')]
    public const RETRY = 'retry';

    #[Transition(self::WAITING_REVIEW, LabelPlace::Finished)]
    public const TRANSITION_FINISH_PROCESSING = 'finish';
}
