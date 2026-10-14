<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Twig;

use Symfony\UX\Icons\IconRendererInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class WorkflowIconExtension extends AbstractExtension
{
    public function __construct(private readonly ?IconRendererInterface $renderer = null)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('workflow_icon', $this->render(...), ['is_safe' => ['html']])];
    }

    public function render(?string $icon, ?string $class = null): string
    {
        if (null === $this->renderer || null === $icon || '' === $icon) {
            return '';
        }

        return $this->renderer->renderIcon($icon, ['class' => trim('wf-icon '.$class), 'aria-hidden' => 'true']);
    }
}
