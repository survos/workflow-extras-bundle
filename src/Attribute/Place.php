<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Attribute;

use Symfony\Component\Workflow\Attribute\Place as NativePlace;

/**
 * Native Place (description, metadata, initial) plus an ordered `next` list.
 * Native owns `description` (symfony/symfony#66872).
 */
#[\Attribute(\Attribute::TARGET_CLASS_CONSTANT)]
class Place extends NativePlace
{
    public function __construct(?string $description = null, array $metadata = [], bool $initial = false, ?array $next = null)
    {
        parent::__construct(description: $description, metadata: Metadata::enhance($metadata, $next), initial: $initial);
    }
}
