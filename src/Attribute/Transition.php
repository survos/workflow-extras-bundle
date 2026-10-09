<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Attribute;

use Symfony\Component\Workflow\Arc;
use Symfony\Component\Workflow\Attribute\Transition as NativeTransition;

#[\Attribute(\Attribute::TARGET_CLASS_CONSTANT | \Attribute::IS_REPEATABLE)]
class Transition extends NativeTransition
{
    public function __construct(
        \BackedEnum|Arc|string|array $from,
        \BackedEnum|Arc|string|array $to,
        ?string $description = null,
        ?string $guard = null,
        array $metadata = [],
        ?array $next = null,
        ?bool $async = null,
        ?string $transport = null,
    ) {
        foreach (['async' => $async, 'transport' => $transport] as $key => $value) {
            if ($value === null) {
                continue;
            }
            if (array_key_exists($key, $metadata) && $metadata[$key] !== $value) {
                throw new \InvalidArgumentException(sprintf('Use the "%s" argument instead of conflicting metadata.', $key));
            }
            $metadata[$key] = $value;
        }
        if (($metadata['transport'] ?? null) !== null && ($metadata['async'] ?? false) !== true) {
            throw new \InvalidArgumentException('A transport requires async: true.');
        }
        parent::__construct($from, $to, $guard, Metadata::enhance($metadata, $description, $next));
    }
}
