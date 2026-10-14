<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Attribute;

use Symfony\Component\Workflow\Attribute\Place as NativePlace;

/** Native place metadata with a label and ordered next-transition candidates. */
#[\Attribute(\Attribute::TARGET_CLASS_CONSTANT)]
class Place extends NativePlace
{
    /** @param string|list<string>|null $next Ordered candidates; selection does not execute them. */
    public function __construct(
        ?string $description = null,
        ?string $label = null,
        string|array|null $next = null,
        bool $initial = false,
        array $metadata = [],
        ?string $icon = null,
        ?string $iconClass = null,
    ) {
        parent::__construct(description: $description, metadata: Metadata::enhance($metadata, $next, $label, $icon, $iconClass), initial: $initial);
    }
}
