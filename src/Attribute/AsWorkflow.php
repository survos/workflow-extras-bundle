<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Attribute;

use Symfony\Component\Workflow\Attribute\AsWorkflow as NativeWorkflow;

/**
 * Native AsWorkflow, unchanged: `description` is native since symfony/symfony#66872.
 * Kept as the Survos entry point for future workflow-level conveniences.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AsWorkflow extends NativeWorkflow
{
}
