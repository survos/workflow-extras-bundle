<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Attribute;

use Symfony\Component\Workflow\Attribute\AsWorkflow as NativeWorkflow;
use Symfony\Component\Workflow\WorkflowType;

#[\Attribute(\Attribute::TARGET_CLASS)]
class AsWorkflow extends NativeWorkflow
{
    public function __construct(
        ?string $name = null,
        WorkflowType $type = WorkflowType::StateMachine,
        string|array $supports = [],
        ?string $supportStrategy = null,
        \BackedEnum|string|array|null $initialMarking = null,
        ?string $markingProperty = null,
        ?string $markingStore = null,
        array $metadata = [],
        bool $auditTrail = false,
        ?array $eventsToDispatch = null,
        array $definitionValidators = [],
        ?string $places = null,
        ?string $description = null,
    ) {
        parent::__construct($name, $type, $supports, $supportStrategy, $initialMarking, $markingProperty, $markingStore, Metadata::enhance($metadata, $description), $auditTrail, $eventsToDispatch, $definitionValidators, $places);
    }
}
