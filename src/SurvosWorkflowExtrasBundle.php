<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle;

use Survos\Kit\AbstractSurvosBundle;
use Survos\Kit\SurvosKitBundle;
use Survos\WorkflowExtrasBundle\Service\NextTransitionResolver;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Workflow\Attribute\AsWorkflow;
use Symfony\Component\Workflow\WorkflowBundle;

#[RequiredBundle(SurvosKitBundle::class)]
#[RequiredBundle(WorkflowBundle::class)]
// Symfony\Component\HttpKernel\Bundle\Bundle <-- Flex auto-registration marker (see Survos\Kit\AbstractSurvosBundle)
final class SurvosWorkflowExtrasBundle extends AbstractSurvosBundle
{
    public const ASSET_PACKAGE = '@survos/workflow-extras-bundle';

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->registerForAutoconfiguration(Contract\WorkflowContinuationInterface::class)->addTag('workflow_extras.continuation');
        $container->registerAttributeForAutoconfiguration(AsWorkflow::class, static function (ChildDefinition $definition) {
            $definition->addTag('.workflow_extras.labels');
        });
        $container->addCompilerPass(new Compiler\DefaultLabelsPass(), priority: 1);
        $container->addCompilerPass(new Compiler\WorkflowCatalogPass(), priority: 0);
        // Native attribute registration runs at priority 2.
        $container->addCompilerPass(new Compiler\RequireAsyncRuntimePass(), priority: 1);
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        parent::loadExtension($config, $container, $builder);
        $container->services()->set(NextTransitionResolver::class);
        if (class_exists(\Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class)) {
            $container->services()->set(EventListener\WorkflowKickoffListener::class)
                ->autoconfigure()
                ->arg('$continuations', \Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator('workflow_extras.continuation'));
        }
        if (class_exists(\Twig\Extension\AbstractExtension::class)) {
            $container->services()->set(Twig\WorkflowIconExtension::class)
                ->autowire()->tag('twig.extension');
        }
    }
}
