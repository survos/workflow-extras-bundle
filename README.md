# Workflow Extras Bundle

Small conveniences on top of **native Symfony 8.2 workflow attributes**. Symfony
continues to discover and register the workflow, validate its definition and
execute its transitions. There is no Survos definition reader or registrar here.

**Experimental 0.1 scaffold**, requiring PHP 8.5 and `8.2.*@dev` integration
packages. This is not a drop-in replacement for state-bundle yet.

## Attributes

```php
use Survos\WorkflowExtrasBundle\Attribute\AsWorkflow;
use Survos\WorkflowExtrasBundle\Attribute\Place;
use Survos\WorkflowExtrasBundle\Attribute\Transition;

#[AsWorkflow(name: 'packages', supports: Package::class, description: 'Enrich packages')]
final class PackageWorkflow
{
    #[Place('Package discovered', initial: true, next: [self::LOAD])]
    public const DISCOVERED = 'discovered';

    #[Place('Package metadata loaded')]
    public const LOADED = 'loaded';

    #[Transition(
        self::DISCOVERED,
        self::LOADED,
        'Fetch package metadata',
        label: 'Load',
    )]
    public const LOAD = 'load';
}
```

These classes extend Symfony's corresponding attributes, enabled by merged
[Symfony #66687](https://github.com/symfony/symfony/pull/66687).
`Place(initial: true)` is native through
[#66722](https://github.com/symfony/symfony/pull/66722), and `description` on
`AsWorkflow`, `Place` and `Transition` is native through
[#66872](https://github.com/symfony/symfony/pull/66872): extras passes it to
the native attribute and stores nothing itself.
Both transition endpoints reference declared place constants, so each stored place
name is defined once. We explicitly declare and describe every place and
transition instead of relying on inferred places, making the definition useful
to humans and tools. Native backed enum cases and Arc arguments remain usable;
enums are optional.

| Attribute | Arguments |
| --- | --- |
| `AsWorkflow` | Identical to native (including `description`); kept for future workflow-level extras. |
| `Place` | `description`, `label`, `next`, `initial`, `metadata`, `icon`, `iconClass`. |
| `Transition` | `from`, `to`, `description`, `label`, `next`, `guard`, `metadata`, `icon`, `iconClass`. |

These convenience signatures intentionally differ from native positional arguments.
Use named arguments after the description when migrating existing declarations.
`label` and `next` become native metadata. `next` accepts a transition name or an
ordered list, normalized to a list; null omits it and an empty list stays empty.
Metadata-only `next` values are normalized too. Names must be non-empty strings;
associative lists and conflicting explicit/metadata values are rejected.
A matching label or normalized next value in both locations is accepted.
Other metadata is preserved. Native description conflict rules still apply.
Native parent properties remain readonly, and Transition remains repeatable.
Do not apply both a native and derived Place/AsWorkflow to the same target.

With the extras bundle enabled, missing labels are filled during container
compilation from the PHP enum case or constant name: `Discovered = 'new'` displays
"Discovered", and `FETCH_DOCS = 'fetch_docs'` displays "Fetch docs". CamelCase and
underscores are humanized to sentence case; legacy `PLACE_` / `TRANSITION_`
prefixes are omitted. Explicit labels (including metadata labels) win. The generated
label is ordinary metadata, shared by native dumpers and the explorer. Direct
attribute construction without container compilation cannot infer its declaration
name. Stored identifiers remain unchanged.

Place attributes can be applied to string-backed enum cases; pass the enum class
as `AsWorkflow(places: PackagePlace::class)` and use cases directly as transition
endpoints. Place labels, descriptions, initial flags and next candidates are read
by Symfony's native attribute reader.

Native descriptions came from
[#66726](https://github.com/symfony/symfony/issues/66726), implemented in
[#66872](https://github.com/symfony/symfony/pull/66872) and made description-first
in [#66890](https://github.com/symfony/symfony/pull/66890). Core's dumpers already
read them (`workflow:dump --with-metadata`, PlantUML), as does this bundle's optional explorer template.

## Next selection, without automatic chaining

```php
$next = $resolver->firstEnabled($workflow, $subject, ['validate', 'reject']);
if ($next !== null) {
    // Apply synchronously, or persist/commit and explicitly dispatch through async.
}
```

Inject `Survos\WorkflowExtrasBundle\Service\NextTransitionResolver`. It validates
the entire candidate list, then selects the first transition enabled by native
guards. It does not apply, enqueue, flush or commit anything. Native `can()` can
initialize marking and run guard listeners, so selection is not guaranteed to be
side-effect-free. This scaffold intentionally has no automatic entered/completed
listener: safe persistence and chaining are a later integration milestone.

## Installation

After publication, in an application whose Symfony constraints allow 8.2-dev:

```bash
composer config minimum-stability dev
composer config prefer-stable true
composer require 'survos/workflow-extras-bundle:dev-main' --with-all-dependencies
```

Review and lock the resolved versions. Enable
`Survos\WorkflowExtrasBundle\SurvosWorkflowExtrasBundle` if Flex has not done so.
It requires WorkflowBundle and Survos Kit, not FrameworkBundle, Doctrine ORM,
Twig or Messenger. Kit itself requires PHP 8.5 and HttpKernel.

Extras owns common attributes and next selection. It does not declare typed
`async` or `transport` arguments. For queued paths use
`Survos\WorkflowAsyncBundle\Attribute\Transition` (often aliased as
`AsyncTransition`); that attribute extends extras, retaining every common argument
in the same position and appending `async` and `transport`. Async requires extras;
extras remains usable without Messenger or async. Native metadata remains an
interoperability option. A definition with `async: true` but no enabled async
runtime fails compilation with an installation/configuration hint.

## Current scope and roadmap

| Capability | Status |
| --- | --- |
| Derived AsWorkflow/Place/Transition and metadata conveniences | Implemented |
| Descriptions ([#66872](https://github.com/symfony/symfony/pull/66872)) | Native, description-first ([#66890](https://github.com/symfony/symfony/pull/66890)) |
| Ordered first-enabled transition selection | Implemented |
| Automatic `next` chaining after safe persistence | Planned |
| Workflow iteration commands | Application-owned in packages; no generic persistence command yet |
| Explorer template, graph controller and grouped transition paths | Implemented optional integration |
| State-bundle migration and deprecation | After validated replacements and migration documentation |

The first example is the packages workflow: metadata and README fetching on
separate queues, with ordinary validation between them. Start with
[the example in mono](https://github.com/survos/mono/tree/main/bu/workflow-async-bundle/examples/packages).
Its test suite also runs this bundle's tests. The next application checkpoints are
searchbench and harvest. The packages application now uses both bundles without
state-bundle; its `state:iterate` and `state:stats` commands remain application-owned.

From a standalone bundle checkout, `composer install` followed by `composer test`
runs its focused attribute/selector tests. The mono example additionally tests
native discovery and real Messenger execution with both bundles enabled.

## Workflow catalog and optional explorer

Inject `Survos\WorkflowExtrasBundle\Service\WorkflowCatalog` for tooling that
needs workflow declarations without constructing an entity. `all()` returns
names with declared `supports`, `definition_class` and `type`; `forClass()` returns
all matching names, including interface matches; `get()` retrieves the native
workflow service. Runtime eligibility remains Symfony Registry's responsibility,
especially for custom support strategies.

The catalog consumes native workflow service tags. Until
[Symfony #66926](https://github.com/symfony/symfony/pull/66926) lands, the existing
attribute label pass fills missing declaration fields for extras workflows.
Native tag values take precedence. Configuration-defined workflows without those
native fields remain available by name, with unknown support declarations.

The optional `@SurvosWorkflowExtras/workflow.html.twig` template and
`survos--workflow-extras-bundle--workflow` Stimulus controller provide the explorer.
The consuming application owns the route, access control and Twig/Stimulus setup.
Use `TransitionGroups::forWorkflow()` for its transition sidebar: repeated names
with identical metadata share a heading while each path remains explicit.
Different guards stay separate, and multi-place requirements are not merged.
`WorkflowGraphvizDumper` draws state machines; use Symfony's native
`GraphvizDumper` for workflows with multiple simultaneous places.

## Optional icons

Place and Transition accept `icon` (for example `tabler:circle-check`) and
`iconClass` (for example `text-success`). These become native metadata, including
when inherited by async transitions. Conflicting explicit and metadata values
are rejected. Async-only arguments follow all extras arguments; prefer named
arguments when adopting new options.

With Symfony UX Icons enabled, the explorer renders these beside place and
transition labels. Without UX Icons it keeps the labels and omits icons. Twig
consumers can use `workflow_icon(icon, iconClass)` for consistent rendering.
The async execution indicator remains separate. No inline iconStyle is provided.
