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
        async: true,
        transport: 'package_metadata',
    )]
    public const LOAD = 'load';
}
```

These classes extend Symfony's corresponding attributes, enabled by merged
[Symfony #66687](https://github.com/symfony/symfony/pull/66687).
`Place(initial: true)` is native through
[#66722](https://github.com/symfony/symfony/pull/66722), and `description` on
`AsWorkflow`, `Place` and `Transition` is native through
[#66872](https://github.com/symfony/symfony/pull/66872): extras remaps its
arguments onto the native ones by name and stores nothing itself.
Both transition endpoints reference declared place constants, so each stored place
name is defined once. We explicitly declare and describe every place and
transition instead of relying on inferred places, making the definition useful
to humans and tools. Native backed enum cases and Arc arguments remain usable;
enums are optional.

| Attribute | Arguments |
| --- | --- |
| `AsWorkflow` | Identical to native (including `description`); kept for future workflow-level extras. |
| `Place` | `description`, `initial`, `metadata`, `next`. |
| `Transition` | Required `from`, `to`, then `description`, `guard`, `metadata`, `next`, optional `async` and `transport`. |

Positions differ from native, which is `Place(description, metadata, initial)`
and `Transition(from, to, guard, description, metadata)`. In particular, the
third positional `Transition` argument is a description here but a guard
expression natively, so name it (`description:`) when switching an import to
the native attribute. [#66890](https://github.com/symfony/symfony/pull/66890)
proposes the description-first `Transition(from, to, description, guard, metadata)`
in core before 8.2 is released; once merged, the positions match. Ordered `next` lists become ordinary metadata. As in core,
a `description` argument together with a `description` metadata key is rejected.
All other metadata is preserved. Next lists must contain non-empty strings.
Native parent properties remain readonly, and Transition remains repeatable.
Do not apply both a native and derived Place/AsWorkflow to the same target;
Symfony rejects that ambiguity.

Native descriptions came from
[#66726](https://github.com/symfony/symfony/issues/66726), implemented in
[#66872](https://github.com/symfony/symfony/pull/66872). Core's dumpers already
read them (`workflow:dump --with-metadata`, PlantUML), as does state-bundle's
explorer.

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

Async metadata in the example has no execution effect by itself. Install
[workflow-async-bundle](https://github.com/survos/mono/tree/main/bu/workflow-async-bundle)
and explicitly dispatch to get queued execution. Extras does not require async;
async does not require extras. Their integration is through native metadata.
Extras accepts `async: true, transport: 'package_metadata'` as typed conveniences
and writes the native metadata consumed by workflow-async-bundle. Omitted options
preserve existing metadata; conflicting typed values are rejected. The runtime is
an optional dependency: install both bundles for queued transitions, or extras
alone for descriptions and next selection. If a native definition contains
`async: true` without the async runtime enabled, container compilation fails with
an installation/configuration hint. Installing the package alone is insufficient:
enable the bundle and configure a subject store and consumable Messenger transport.

You may also use async's typed Transition with extras' AsWorkflow and Place;
async's Transition follows native argument order, including `description`.

## Current scope and roadmap

| Capability | Status |
| --- | --- |
| Derived AsWorkflow/Place/Transition and metadata conveniences | Implemented |
| Descriptions ([#66872](https://github.com/symfony/symfony/pull/66872)) | Native; extras remaps onto it. Description-first native `Transition` proposed in [#66890](https://github.com/symfony/symfony/pull/66890) |
| Ordered first-enabled transition selection | Implemented |
| Automatic `next` chaining after safe persistence | Planned |
| Workflow iteration command (`state:iterate` successor) | Planned here, not in async |
| Explorer, place/transition tables, diagram icons and Twig helpers | Planned optional integration |
| State-bundle migration and deprecation | After validated replacements and migration documentation |

The first example is the packages workflow: metadata and README fetching on
separate queues, with ordinary validation between them. Start with
[the example in mono](https://github.com/survos/mono/tree/main/bu/workflow-async-bundle/examples/packages).
Its test suite also runs this bundle's tests. The next application checkpoints are
packages, searchbench and harvest; these applications have not been migrated by
this scaffold.

From a standalone bundle checkout, `composer install` followed by `composer test`
runs its focused attribute/selector tests. The mono example additionally tests
native discovery and real Messenger execution with both bundles enabled.
