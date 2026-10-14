<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle;

use Survos\Kit\AbstractSurvosBundle;
use Survos\Kit\Routing\ConfigurableRoutesInterface;
use Survos\Kit\SurvosKitBundle;
use Survos\Kit\Traits\HasConfigurableRoutes;
use Survos\WorkflowExtrasBundle\Service\NextTransitionResolver;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Workflow\WorkflowBundle;

#[RequiredBundle(SurvosKitBundle::class)]
#[RequiredBundle(WorkflowBundle::class)]
// Symfony\Component\HttpKernel\Bundle\Bundle <-- Flex auto-registration marker (see Survos\Kit\AbstractSurvosBundle)
final class SurvosWorkflowExtrasBundle extends AbstractSurvosBundle implements ConfigurableRoutesInterface
{
    use HasConfigurableRoutes;

    public const ASSET_PACKAGE = '@survos/workflow-extras-bundle';

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new Compiler\DefaultLabelsPass(), priority: 1);
        $container->addCompilerPass(new Compiler\WorkflowCatalogPass(), priority: 0);
        // Native attribute registration runs at priority 2.
        $container->addCompilerPass(new Compiler\RequireAsyncRuntimePass(), priority: 1);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        // The read-only explorer, /workflow/{flowCode}.
        $this->addRouteOptions($definition->rootNode()->children(), '/workflow');
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        parent::loadExtension($config, $container, $builder);
        $container->services()->set(NextTransitionResolver::class);
        $container->services()->set(Service\DirectTransitionExecutor::class);
        if (!$builder->hasAlias(Contract\TransitionExecutorInterface::class)) {
            $container->services()->alias(Contract\TransitionExecutorInterface::class, Service\DirectTransitionExecutor::class);
        }
        $container->services()->set(Service\NextTransitionRunner::class)->autowire();
        $container->services()->set(EventListener\NextTransitionListener::class)->autowire()
            ->tag('kernel.event_listener', ['event' => 'workflow.completed', 'method' => 'completed', 'priority' => -1024]);
        if (class_exists(\Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class)) {
            $container->services()->set(EventListener\WorkflowKickoffListener::class)
                ->autowire()->autoconfigure();
            if (class_exists(\Symfony\Component\Console\Command\Command::class)) {
                // state:iterate and state:stats
                $container->services()->set(Doctrine\WorkflowEntityCommands::class)
                    ->autowire()->autoconfigure();
            }
        }
        if (class_exists(\Twig\Extension\AbstractExtension::class)) {
            $container->services()->set(Twig\WorkflowIconExtension::class)
                ->autowire()->tag('twig.extension');
        }
    }
}
