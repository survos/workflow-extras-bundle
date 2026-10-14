<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Tests;

use Knp\Menu\MenuFactory;
use PHPUnit\Framework\TestCase;
use Survos\MenuBundle\Event\MenuEvent;
use Survos\WorkflowExtrasBundle\Menu\WorkflowMenuListener;
use Survos\WorkflowExtrasBundle\Service\WorkflowCatalog;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class WorkflowMenuListenerTest extends TestCase
{
    public function testMenuIsSortedAndLinksToExplorer(): void
    {
        $routes = new RouteCollection();
        $routes->add('survos_workflow_extras', new Route('/workflow/{flowCode}'));
        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($routes);
        $router->expects(self::exactly(2))->method('generate')->willReturnCallback(static fn ($route, $params) => '/workflow/'.$params['flowCode']);
        $factory = new MenuFactory();
        $root = $factory->createItem('root');
        $listener = new WorkflowMenuListener(new WorkflowCatalog(new ServiceLocator([]), ['zebra' => [], 'archive' => []]), $router);
        $listener->workflows(new MenuEvent($root, $factory));
        $menu = $root->getChild('Workflows');
        self::assertNotNull($menu);
        self::assertSame(['archive', 'zebra'], array_keys($menu->getChildren()));
        self::assertSame('/workflow/archive', $menu->getChild('archive')->getUri());
    }

    public function testNoMenuWithoutWorkflowsOrExplorer(): void
    {
        foreach ([[], ['archive' => []]] as $definitions) {
            $router = $this->createMock(RouterInterface::class);
            $router->method('getRouteCollection')->willReturn(new RouteCollection());
            $router->expects(self::never())->method('generate');
            $factory = new MenuFactory();
            $root = $factory->createItem('root');
            (new WorkflowMenuListener(new WorkflowCatalog(new ServiceLocator([]), $definitions), $router))->workflows(new MenuEvent($root, $factory));
            self::assertCount(0, $root->getChildren());
        }
    }
}
