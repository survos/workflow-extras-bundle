<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Attribute;

use Symfony\Component\Workflow\Attribute\Place as NativePlace;

#[\Attribute(\Attribute::TARGET_CLASS_CONSTANT)]
class Place extends NativePlace
{
    public function __construct(?string $description = null, bool $initial = false, array $metadata = [], ?array $next = null)
    {
        parent::__construct(Metadata::enhance($metadata, $description, $next), $initial);
    }
}
