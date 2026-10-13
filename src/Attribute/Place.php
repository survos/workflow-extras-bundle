<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Attribute;

use Symfony\Component\Workflow\Attribute\Place as NativePlace;

/**
 * Description-first Place plus an ordered `next` list. Arguments are remapped by
 * name onto the native attribute, which owns `description` (symfony/symfony#66872).
 */
#[\Attribute(\Attribute::TARGET_CLASS_CONSTANT)]
class Place extends NativePlace
{
    public function __construct(?string $description = null, bool $initial = false, array $metadata = [], ?array $next = null)
    {
        parent::__construct(description: $description, metadata: Metadata::enhance($metadata, $next), initial: $initial);
    }
}
