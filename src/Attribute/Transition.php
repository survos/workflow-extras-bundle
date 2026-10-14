<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Attribute;

use Symfony\Component\Workflow\Arc;
use Symfony\Component\Workflow\Attribute\Transition as NativeTransition;

/** Native transition with a label and ordered next-transition candidates. */
#[\Attribute(\Attribute::TARGET_CLASS_CONSTANT | \Attribute::IS_REPEATABLE)]
class Transition extends NativeTransition
{
    /** @param string|list<string>|null $next Ordered candidates; selection does not execute them. */
    public function __construct(
        \BackedEnum|Arc|string|array $from,
        \BackedEnum|Arc|string|array $to,
        ?string $description = null,
        ?string $label = null,
        string|array|null $next = null,
        ?string $guard = null,
        array $metadata = [],
        ?string $icon = null,
        ?string $iconClass = null,
    ) {
        // Keep the explorer's expression identical to the native execution guard.
        if (null !== $guard) {
            $metadata['guard'] = $guard;
        }
        parent::__construct($from, $to, description: $description, guard: $guard, metadata: Metadata::enhance($metadata, $next, $label, $icon, $iconClass));
    }
}
