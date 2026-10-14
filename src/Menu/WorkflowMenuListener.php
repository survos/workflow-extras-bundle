<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Menu;

use Survos\MenuBundle\Attribute\AsMenuListener;
use Survos\MenuBundle\Event\MenuEvent;
use Survos\MenuBundle\MenuSlot;
use Survos\WorkflowExtrasBundle\Service\WorkflowCatalog;
use Symfony\Component\Routing\RouterInterface;

final readonly class WorkflowMenuListener
{
    public function __construct(private WorkflowCatalog $catalog, private RouterInterface $router)
    {
    }

    #[AsMenuListener(MenuSlot::ADMIN_NAVBAR_MENU)]
    public function workflows(MenuEvent $event): void
    {
        $names = array_keys($this->catalog->all());
        if ([] === $names || null === $this->router->getRouteCollection()->get('survos_workflow_extras')) {
            return;
        }
        sort($names);
        $menu = $event->getMenu()->addChild('Workflows');
        $menu->setExtra('icon', 'tabler:sitemap');
        $menu->setExtra('translation_domain', false);
        foreach ($names as $name) {
            $menu->addChild($name, ['uri' => $this->router->generate('survos_workflow_extras', ['flowCode' => $name])])
                ->setExtra('translation_domain', false);
        }
    }
}
