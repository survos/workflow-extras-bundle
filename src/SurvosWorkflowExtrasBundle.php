<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle;

use Survos\Kit\AbstractSurvosBundle;
use Survos\Kit\SurvosKitBundle;
use Survos\WorkflowExtrasBundle\Service\NextTransitionResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Workflow\WorkflowBundle;

#[RequiredBundle(SurvosKitBundle::class)]
#[RequiredBundle(WorkflowBundle::class)]
// Symfony\Component\HttpKernel\Bundle\Bundle <-- Flex auto-registration marker (see Survos\Kit\AbstractSurvosBundle)
final class SurvosWorkflowExtrasBundle extends AbstractSurvosBundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        // Native attribute registration runs at priority 2.
        $container->addCompilerPass(new \Survos\WorkflowExtrasBundle\Compiler\RequireAsyncRuntimePass(), priority: 1);
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        parent::loadExtension($config, $container, $builder);
        $container->services()->set(NextTransitionResolver::class);
    }
}
