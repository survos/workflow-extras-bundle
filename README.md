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
        metadata: ['async' => true, 'transport' => 'package_metadata'],
    )]
    public const LOAD = 'load';
}
```

These classes extend Symfony's corresponding attributes, enabled by merged
[Symfony #66687](https://github.com/symfony/symfony/pull/66687).
`Place(initial: true)` is now native through
[#66722](https://github.com/symfony/symfony/pull/66722), not an extras policy.
Both transition endpoints reference declared place constants, so each stored place
name is defined once. We explicitly declare and describe every place and
transition instead of relying on inferred places, making the definition useful
to humans and tools. Native backed enum cases and Arc arguments remain usable;
enums are optional.

| Attribute | Convenience arguments |
| --- | --- |
| `AsWorkflow` | Native argument order plus optional `description:` at the end. |
| `Place` | `description`, `initial`, `metadata`, `next`. |
| `Transition` | Required `from`, `to`, then `description`, `guard`, `metadata`, `next`. |

Descriptions and ordered `next` lists become ordinary metadata. Non-null typed
values override their matching metadata keys; null leaves metadata unchanged.
All other metadata is preserved. Next lists must contain non-empty strings.
Native parent properties remain readonly, and Transition remains repeatable.
Do not apply both a native and derived Place/AsWorkflow to the same target;
Symfony rejects that ambiguity.

Description-first Place follows the convenience of Console's `Argument` and
`Option`. [#66726](https://github.com/symfony/symfony/issues/66726) proposes native
descriptions; it is independent of this package's implementation. If core adopts
these conveniences, extras can delegate and eventually deprecate its equivalent.

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
You may also use async's typed Transition with extras' AsWorkflow and Place,
putting a transition description in its native metadata argument.

## Current scope and roadmap

| Capability | Status |
| --- | --- |
| Derived AsWorkflow/Place/Transition and metadata conveniences | Implemented |
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
