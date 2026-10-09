<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Attribute;

/** @internal */
final class Metadata
{
    public static function enhance(array $metadata, ?string $description, ?array $next = null): array
    {
        if ($description !== null) {
            $metadata['description'] = $description;
        }
        if ($next !== null) {
            if (!array_is_list($next)) {
                throw new \InvalidArgumentException('Next transitions must be an ordered list.');
            }
            foreach ($next as $name) {
                if (!is_string($name) || $name === '') {
                    throw new \InvalidArgumentException('Next transition names must be non-empty strings.');
                }
            }
            $metadata['next'] = $next;
        }

        return $metadata;
    }
}
