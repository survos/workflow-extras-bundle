<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Tests;

use PHPUnit\Framework\TestCase;
use Survos\WorkflowExtrasBundle\Compiler\WorkflowCatalogPass;
use Survos\WorkflowExtrasBundle\Service\WorkflowCatalog;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Workflow\WorkflowInterface;

final class WorkflowCatalogTest extends TestCase
{
    public function testSeveralWorkflowsCanDeclareTheSameSupportedInterface(): void
    {
        $workflow = $this->createStub(WorkflowInterface::class);
        $catalog = new WorkflowCatalog(new ServiceLocator(['ocr' => static fn () => $workflow]), [
            'ocr' => ['supports' => [\DateTimeInterface::class], 'support_strategy' => null, 'source_class' => null, 'type' => 'workflow'],
            'translation' => ['supports' => [\DateTimeInterface::class, \DateTimeImmutable::class], 'support_strategy' => null, 'source_class' => null, 'type' => 'workflow'],
            'runtime_strategy' => ['supports' => [], 'support_strategy' => 'app.support', 'source_class' => null, 'type' => 'state_machine'],
        ]);
        self::assertSame(['ocr', 'translation'], $catalog->forClass(\DateTimeImmutable::class));
        self::assertSame([], $catalog->forClass(\stdClass::class));
        self::assertSame($workflow, $catalog->get('ocr'));
    }

    public function testCompilerCopiesNativeDeclarationTags(): void
    {
        $container = new ContainerBuilder();
        $container->register('workflow.ocr')->setArguments([null, (new ChildDefinition('workflow.marking_store.method'))->setArguments([false, 'places'])])->addTag('workflow', ['name' => 'ocr', 'supports' => [\DateTimeInterface::class], 'support_strategy' => null, 'source_class' => self::class])->addTag('workflow.workflow');
        $container->register('state_machine.custom')->setArguments([null, new Reference('app.marking_store')])->addTag('workflow', ['name' => 'custom', 'supports' => [], 'support_strategy' => 'app.support', 'source_class' => null])->addTag('workflow.state_machine');
        (new WorkflowCatalogPass())->process($container);
        self::assertSame([
            'custom' => ['supports' => [], 'support_strategy' => 'app.support', 'source_class' => null, 'type' => 'state_machine', 'marking_property' => null],
            'ocr' => ['supports' => [\DateTimeInterface::class], 'support_strategy' => null, 'source_class' => self::class, 'type' => 'workflow', 'marking_property' => 'places'],
        ], $container->getDefinition(WorkflowCatalog::class)->getArgument(1));
    }
}
