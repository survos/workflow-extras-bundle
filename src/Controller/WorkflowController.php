<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Controller;

use Survos\WorkflowExtrasBundle\Service\TransitionGroups;
use Survos\WorkflowExtrasBundle\Service\WorkflowCatalog;
use Survos\WorkflowExtrasBundle\Service\WorkflowGraphvizDumper;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Workflow\Dumper\GraphvizDumper;
use Symfony\Component\Workflow\Marking;
use Twig\Environment;

/**
 * The workflow explorer. Routed under `survos_workflow_extras.route_prefix`
 * (default /workflow); `routes_enabled: false` keeps it hidden.
 */
#[AsController]
final readonly class WorkflowController
{
    public function __construct(
        private WorkflowCatalog $catalog,
        private ?Environment $twig = null,
        private ?RouterInterface $router = null,
    ) {
    }

    #[Route('/{flowCode}', name: 'survos_workflow_extras', methods: ['GET'])]
    public function show(string $flowCode): Response
    {
        $twig = $this->twig ?? throw new \LogicException('The workflow explorer needs Twig: composer require symfony/twig-bundle.');
        $definition = $this->catalog->all()[$flowCode] ?? throw new NotFoundHttpException('Unknown workflow.');
        $workflow = $this->catalog->get($flowCode);
        $dumper = 'state_machine' === $definition['type'] ? new WorkflowGraphvizDumper() : new GraphvizDumper();

        return new Response($twig->render('@SurvosWorkflowExtras/workflow.html.twig', [
            'flowCode' => $flowCode,
            'workflow' => $workflow,
            'definition' => $workflow->getDefinition(),
            'transitionGroups' => (new TransitionGroups())->forWorkflow($workflow),
            'classes' => $definition['supports'],
            'entity' => null,
            'commandRouteExists' => null !== $this->router?->getRouteCollection()->get('survos_command'),
            'digraph' => $dumper->dump($workflow->getDefinition(), new Marking(array_fill_keys($workflow->getDefinition()->getInitialPlaces(), 1)), [
                'graph' => ['bgcolor' => 'transparent', 'rankdir' => 'TB', 'pad' => '0.35', 'nodesep' => '0.45', 'ranksep' => '0.65'],
                'node' => ['fontsize' => '13', 'fontname' => 'Arial'],
                'edge' => ['color' => '#94a3b8', 'fontcolor' => '#52647a', 'fontsize' => '10'],
            ]),
        ]));
    }
}
