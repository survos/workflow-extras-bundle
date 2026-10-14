<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Tests;

use PHPUnit\Framework\TestCase;
use Survos\WorkflowExtrasBundle\Compiler\WorkflowCatalogPass;
use Survos\WorkflowExtrasBundle\Service\WorkflowCatalog;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Workflow\WorkflowInterface;

final class WorkflowCatalogTest extends TestCase
{
    public function testSeveralWorkflowsCanDeclareTheSameSupportedInterface(): void
    {
        $workflow = $this->createStub(WorkflowInterface::class);
        $catalog = new WorkflowCatalog(new ServiceLocator(['ocr' => static fn () => $workflow]), [
            'ocr' => ['supports' => [\DateTimeInterface::class], 'definition_class' => null, 'type' => 'workflow'],
            'translation' => ['supports' => [\DateTimeInterface::class, \DateTimeImmutable::class], 'definition_class' => null, 'type' => 'workflow'],
            'runtime_strategy' => ['supports' => [], 'definition_class' => null, 'type' => 'state_machine'],
        ]);
        self::assertSame(['ocr', 'translation'], $catalog->forClass(\DateTimeImmutable::class));
        self::assertSame([], $catalog->forClass(\stdClass::class));
        self::assertSame($workflow, $catalog->get('ocr'));
    }

    public function testCompilerUsesNativeDeclarationTagsAndKeepsUnknownSupportsEmpty(): void
    {
        $container = new ContainerBuilder();
        $container->register('workflow.ocr')->addTag('workflow', ['name' => 'ocr', 'supports' => [\DateTimeInterface::class], 'definition_class' => self::class])->addTag('workflow.workflow');
        $container->register('state_machine.custom')->addTag('workflow', ['name' => 'custom'])->addTag('workflow.state_machine');
        (new WorkflowCatalogPass())->process($container);
        self::assertSame([
            'custom' => ['supports' => [], 'definition_class' => null, 'type' => 'state_machine'],
            'ocr' => ['supports' => [\DateTimeInterface::class], 'definition_class' => self::class, 'type' => 'workflow'],
        ], $container->getDefinition(WorkflowCatalog::class)->getArgument(1));
    }
}
