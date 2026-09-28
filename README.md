# PHP Sentinel

[![Tests](https://github.com/vaened/php-sentinel/actions/workflows/tests.yml/badge.svg)](https://github.com/vaened/php-sentinel/actions/workflows/tests.yml)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

Framework-agnostic authorization core for PHP 8.4+.

```php
// Registry: handled by a seeder or your admin UI
$admin = $roleRegistry->create('admin', 'Administrator');
$edit  = $permissionRegistry->create('posts.edit', 'Edit Posts');

// Assignment
$granter->grant($admin, $edit);
$granter->grant($user, $admin);

// Evaluation
$authorizer->can($user, ['posts.edit']);   // true
$authorizer->is($user, ['admin']);         // true

// Deny overrides inherited grant
$denier->deny($user, $edit);
$authorizer->can($user, ['posts.edit']);   // false

// Revoke clears any denial or previous assignment
$revoker->revoke($user, $edit);
$authorizer->can($user, ['posts.edit']);   // true again
```

## Installation

```bash
composer require vaened/php-sentinel
```

Requires PHP 8.4 or higher. Its only dependency is `vaened/support`.

The example assumes concrete model and persistence implementations are already wired in your application bootstrap.

## Integration surface

Sentinel provides the [`Authorizer`](src/Authorization/Authorizer.php), the [operators](src/Operators), and the [`Registry`](src/Registry)
services, including the concrete [`PermissionEntryProvider`](src/Authorization/PermissionEntryProvider.php) and
[`RoleEntryProvider`](src/Authorization/RoleEntryProvider.php).
The package consumer implements the domain and repository contracts.

### Foundations

These contracts define the minimum model Sentinel needs to evaluate authorization.

- **Subject**
    - Contract: [`Subject`](src/Subject.php)
    - Represents the subject requesting permissions.
    - Sentinel only requires:
        - `id(): int|string|Identifier`
        - `scope(): Subject|null`
            - `Identifier`: [`Identifier`](src/Identifier.php)

- **Authorization**
    - Contract: [`Authorization`](src/Authorization.php)
    - Defines:
        - `code(): string` — the canonical identity Sentinel works with.
    - Metadata fields live on the entity contracts that need them.
    - Derived contracts:
        - **Role**
            - Contract: [`Role`](src/Role.php)
            - Represents a composite authorization. A role groups permissions.
            - Provides `id()`, `name()`, `description()`, and `scope()` for catalog use.
        - **Permission**
            - Contract: [`Permission`](src/Permission.php)
            - Represents an atomic authorization.
            - Provides `id()`, `name()`, `description()` for catalog use.
        - **SubjectPermission**
            - Contract: [`SubjectPermission`](src/SubjectPermission.php)
            - Represents a `subject ↔ permission` link.
            - Provides `state(): SubjectPermissionState`.
            - State enum: [`SubjectPermissionState`](src/SubjectPermissionState.php)
            - The state distinguishes:
                - `Denied` — direct deny on the subject
                - `Direct` — direct grant on the subject
                - `Inherited` — grant inherited through a role

## Multi-tenant authorization

Sentinel models authorization by tenant, organization, team, or another isolated boundary through `scope()`.

A `scope` is a `Subject`: an authorization entity that can also have roles and permissions. For a tenant to constrain authorizations,
it must itself be a subject with its own authorizations.

### Subjects and tenants

A `Subject` is the authorization object: the entity that receives roles, permissions, and denials. It does not necessarily represent a
user.

In a multi-tenant application, the subject is commonly a membership:

- A user can have multiple memberships.
- Each membership is a distinct subject.
- Each subject has one direct `scope`, or none.
- A membership can belong to an organization, team, or tenant.
- A tenant can have its own roles and permissions, which constrain the authorizations of its subjects.

```php
$membership->scope(); // $organization
```

One scope can itself have a scope. This supports hierarchies; for example, a membership belongs to a team and the team belongs to an
organization.

### Global and tenant roles

A `Role` can also have a `scope`:

- A role without a `scope` is global.
- A role with a `scope` belongs only to that tenant.
- A subject within a tenant can receive global roles.
- A subject within a tenant can receive local roles only from that same tenant.
- A subject without a tenant cannot receive a local role.

| Subject    | Role     | Result   |
|------------|----------|----------|
| No `scope` | Global   | Allowed  |
| No `scope` | Tenant A | Rejected |
| Tenant A   | Global   | Allowed  |
| Tenant A   | Tenant A | Allowed  |
| Tenant A   | Tenant B | Rejected |

### Role catalog

Role codes follow rules that prevent ambiguity:

- The same local code can exist in different tenants.
- Two roles with the same code cannot exist within the same tenant.
- A global role and a local role cannot share a code.

For example, `administrator` can exist in Organization A and Organization B, but it cannot also exist as a global role.

### Permission boundaries

A `scope` caps the permissions that can be granted to its subjects and roles.

Before writing, `grant()` validates:

1. Every role is compatible with the subject's `scope`.
2. A direct permission is allowed by the subject's `scope`.
3. A permission added to a role is allowed by the role's `scope`.
4. Every permission granted by a role is allowed by the subject's `scope` before that role is assigned.

If any scope validation fails, Sentinel rejects the complete operation. It does not create any of the requested authorizations partially.

An explicit denial does not need scope approval because it only reduces access.

`revoke()` does not reject a relationship based on scope: it must always be able to remove an old, invalid, or out-of-band assignment.

### Permission evaluation

`can()` requires a permission to be allowed by the subject and by every `scope` that participates in evaluation.

With `Junction::And`, every requested permission must be allowed at every level.

With `Junction::Or`, at least one same permission must be allowed at every level. Sentinel does not combine different permissions from the
subject and its scope to produce a valid result.

An explicit denial at any level overrides a granted permission.

### Scope propagation

By default, Sentinel evaluates the direct `scope` and all of its ancestors through `TransitiveScopePropagationPolicy`.

Use `DirectScopePropagationPolicy` when only the direct scope must be evaluated.

Cycles are invalid. If a subject eventually points to itself, directly or indirectly, Sentinel throws `ScopeCycleDetected`.

### Roles and scopes

Scopes cap permissions, not role membership.

Therefore, `is()` and `isnt()` only check whether the subject has the role. Use `can()` to evaluate whether that role permits an action
within the tenant.

### Repositories

Repositories persist both the catalog and the relationships between subjects, roles, and permissions.

- **RoleRepository**
    - Contract: [`RoleRepository`](src/Repositories/RoleRepository.php)
    - Stores role records with `id`, `code`, `name`, `description`, and an optional `scope`.
    - `lookup(Subject|null $scope, ...$codes)` returns roles whose scope exactly matches the supplied scope. Pass `null` to look up
      global roles.
    - `match(...$codes)` returns every role with the requested codes, regardless of scope.
    - `create(..., Subject|null $scope = null)` persists either a global role or one scoped to a subject.

- **PermissionRepository**
    - Contract: [`PermissionRepository`](src/Repositories/PermissionRepository.php)
    - Stores permission records with `id`, `code`, `name`, and `description`.
    - `lookup(...)` returns the typed `Permissions` collection (concrete `Permission` instances).

- **SubjectRoleRepository**
    - Contract: [`SubjectRoleRepository`](src/Repositories/SubjectRoleRepository.php)
    - Persists `subject ↔ role` links.
    - `grants($subject, $codes)` resolves permissions inherited through the subject’s roles:
        - `null` resolves every inherited permission.
        - A populated array resolves only matching permission codes.
    - `purge($subject)` removes every role assignment for a subject.

- **SubjectPermissionRepository**
    - Contract: [`SubjectPermissionRepository`](src/Repositories/SubjectPermissionRepository.php)
    - Persists `subject ↔ permission` links.
    - Persisted assignments resolve to `Denied` or `Direct`. `Inherited` is derived at runtime by Sentinel when a permission comes from a
      role.
        - Writes receive `SubjectPermissionSnapshot` value objects.
    - `purge($subject)` removes every direct permission assignment and denial for a subject.

- **RolePermissionRepository**
    - Contract: [`RolePermissionRepository`](src/Repositories/RolePermissionRepository.php)
    - Persists `role ↔ permission` links.
    - Roles only grant permissions; they do not support explicit denials.
    - `grants(Role ...$roles)` returns the deduplicated union of permissions granted by the supplied roles.

Each repository exposes the combination of `lookup`, `grants`, `exists`, `allOf`, `create`, `update`, and `remove` that belongs to its
own contract. Subject role and subject permission repositories also expose `purge($subject)` for complete subject authorization cleanup.

### Entry providers

The entry providers are concrete core services. They compose repositories and return the entries consumed by the `Authorizer`.

- **PermissionEntryProvider**
    - Service: [`PermissionEntryProvider`](src/Authorization/PermissionEntryProvider.php)
    - Encodes the effective precedence of permissions for a subject.
    - Invariants:
        - it only returns codes that were requested;
        - direct subject permissions are resolved first, and missing codes are completed through role grants;
        - **deny overrides inherited grant**: a direct denial on the subject overrides any permission inherited from a role.

- **RoleEntryProvider**
    - Service: [`RoleEntryProvider`](src/Authorization/RoleEntryProvider.php)
    - Resolves which requested role codes are effectively assigned to the subject.
    - It does not define precedence rules.

## Caching the authorization checks

Calls to `can`, `cannot`, `is`, and `isnt` are the hot path of any authorization system. Sentinel provides an optional cache layer
that avoids recomputing a subject's effective authorization projection on every check.

### What gets cached

Sentinel caches the **effective authorization projection of a subject**: the assigned roles and the state of each applicable permission,
whether direct or inherited through a role. That projection is built once per subject and then reused on every subsequent `can` / `is`
check.

For cached permissions, Sentinel preserves the authorization state and its provenance:

- `Denied` — direct deny on the subject
- `Direct` — direct grant on the subject
- `Inherited` — grant inherited through a role
- `DeniedInherited` — direct deny on the subject and a grant inherited through a role
- `DirectInherited` — direct grant on the subject and a grant inherited through a role

**What the cache does NOT do**:

- It does not cache the role or permission catalog. Those entities are cold data. If you remove roles or permissions from the catalog,
  global invalidation causes projections to be rebuilt on the next access.
- It does not cache catalog-to-catalog relations (`role → permission`) for the same reason.
- It does not cache repository `exists()` calls. Those are point validation operations, not part of the hot path.

### Decisions you have to make

Before wiring the layer, there are three operational decisions you need to make:

1. **Provide a PSR-16 driver.** The cache layer is built on top of any
   [`Psr\SimpleCache\CacheInterface`](https://www.php-fig.org/psr/psr-16/) implementation — Redis, Memcached, APCu, file, whatever you
   already use. **Without a driver, there is no cache.** If your framework already gives you a PSR-16 driver (Laravel, Symfony, etc.),
   use it.
2. **Choose a unique `prefix` per application.** Keys are stored as `prefix:...` in the driver. If two applications share the same
   PSR-16 driver (common in multi-tenant setups), they need different prefixes. Suggested convention: `app_name:sentinel`.
3. **Understand what the default `ttl` does.** Sentinel applies a default `ttl` of **12 hours**
   ([`CacheSettings::DEFAULT_TTL_IN_SECONDS`](src/Cache/CacheSettings.php)). A projection lives for exactly that long from the moment it
   is first captured — **it is not renewed on every read**: it is only renewed when it is rebuilt. In practice:

    - If the projection is queried within 12 hours of being captured, it is served from cache.
    - If more than 12 hours pass, the next query rebuilds the projection (a single database hit) and starts a new 12-hour window.
    - An inactive subject leaves its projection orphaned; the driver releases it after the TTL. This keeps cache memory from growing
      without bound if the driver does not have its own eviction policy.

   Pass `ttl: null` if you want projections to never expire by time and prefer to manage cleanup through an external mechanism.

### How to wire it up

First, choose how to construct the factory. Then use that factory to wrap your five base repositories.

#### Using the bundled PSR-16 store

If you already have a driver compatible with [`Psr\SimpleCache\CacheInterface`](https://www.php-fig.org/psr/psr-16/), this is the direct
path:

```php
use Vaened\Sentinel\Cache\CacheSettings;
use Vaened\Sentinel\Cache\SentinelCacheFactory;

$factory = SentinelCacheFactory::from(
    driver:   $psr16Driver,
    settings: new CacheSettings(prefix: 'my_app:sentinel'),
);
```

#### Using your own authorization cache store

If you need a different storage or invalidation strategy, you can implement
[`AuthorizationCacheStore`](src/Cache/AuthorizationCacheStore.php) and hand it to the factory:

```php
use Vaened\Sentinel\Cache\AuthorizationCacheStore;
use Vaened\Sentinel\Cache\SentinelCacheFactory;

$factory = SentinelCacheFactory::as(new MyCustomCacheStore(...));
```

#### Building the cached repositories

Once you have the factory, wrap your five base repositories and get a
[`CachedRepositories`](src/Cache/CachedRepositories.php):

```php
$cached = $factory->build(
    roles:              $myRoleRepo,
    permissions:        $myPermissionRepo,
    rolePermissions:    $myRolePermissionRepo,
    subjectRoles:       $mySubjectRoleRepo,
    subjectPermissions: $mySubjectPermissionRepo,
);

$cached->roleRepository();
$cached->permissionRepository();
$cached->rolePermissionRepository();
$cached->subjectRoleRepository();
$cached->subjectPermissionRepository();
```

The returned repositories **implement the same interfaces as the base ones**. Your consumer code does not change: you pass
`$cached->subjectRoleRepository()` where you used to pass `$mySubjectRoleRepo`.

After a successful subject-role or subject-permission mutation, the cached repository forgets that subject's projection. It never patches
the existing projection in place; the next authorization read rebuilds it from the wrapped repositories.

### Authorization cache store

[`AuthorizationCacheStore`](src/Cache/AuthorizationCacheStore.php) is the contract that encapsulates projection storage, global
invalidation, per-subject invalidation, and construction of the effective cache key.

Stores receive a typed [`SubjectAuthorizationProjection`](src/Projection/SubjectAuthorizationProjection.php). Use `toArray()` when
crossing a serialization boundary and `SubjectAuthorizationProjection::fromArray()` when restoring a payload into a projection.

Sentinel includes a default implementation based on PSR-16:
[`Psr16AuthorizationCacheStore`](src/Cache/Stores/Psr16AuthorizationCacheStore.php).

You can create your own implementation if you need:

- a different invalidation policy;
- a different namespace or versioning strategy;
- integration with tags, events, or framework-specific mechanisms;
- finer control over how orphaned entries are cleaned up.

The main limitation of the default PSR-16 implementation is that, when you call `invalidate()`, previous projections stop being read
immediately, but remain orphaned and continue occupying space in the driver until their TTL expires.

### Invalidating manually

The factory does not expose the internal invalidation services. If you need to invalidate manually (for example, in an artisan command,
after a large migration, or when a specific subject changed roles), instantiate `Psr16AuthorizationCacheStore` separately with the same
driver and the same settings:

```php
use Vaened\Sentinel\Cache\CacheSettings;
use Vaened\Sentinel\Cache\Stores\Psr16AuthorizationCacheStore;

$store = new Psr16AuthorizationCacheStore(
    cache:    $psr16Driver,
    settings: new CacheSettings(prefix: 'my_app:sentinel'),
);

$store->invalidate();           // bumps the global version
$store->forget($subject);       // invalidates one specific subject
```

> **Important:** database mutations performed outside Sentinel operators (direct SQL queries, other processes, improvised seeds) do not
> invalidate the cache automatically. If that happens, call `invalidate()` or `forget()` manually.

### Inspecting the cache

If you inspect your PSR-16 driver with an external tool (Redis Commander, APCu GUI, etc.), you will see keys in this format:

```
{prefix}:version                                # global version counter
{prefix}:v{N}:subject:{class}:{id}:projection   # one subject projection
```

`{N}` is the active namespace version. Every time you call `invalidate()`, all projections move to `v{N+1}` and the previous ones become
orphaned (they are no longer read, and the driver eventually clears them).

## Implementation references

- Reference wiring: [`tests/Integration/AuthorizerFlowTest.php`](tests/Integration/AuthorizerFlowTest.php)
- Reference in-memory implementations: [`tests/Runtime/`](tests/Runtime)
- In-memory repositories: [`tests/Runtime/Repositories/`](tests/Runtime/Repositories)
- Core `PermissionEntryProvider`: [`src/Authorization/PermissionEntryProvider.php`](src/Authorization/PermissionEntryProvider.php)
- Core `RoleEntryProvider`: [`src/Authorization/RoleEntryProvider.php`](src/Authorization/RoleEntryProvider.php)

## Authorizer

[`Authorizer`](src/Authorization/Authorizer.php) is the read gate. It combines a `PermissionEntryProvider` and a `RoleEntryProvider` and
answers boolean questions about a `Subject`. It is constructed once with both providers and is then ready to answer `can`, `cannot`,
`is`, and `isnt` at any time.

Its optional third constructor argument is a [`ScopePropagationPolicy`](src/Propagation/ScopePropagationPolicy.php). Sentinel uses
[`TransitiveScopePropagationPolicy`](src/Propagation/TransitiveScopePropagationPolicy.php) by default; use
[`DirectScopePropagationPolicy`](src/Propagation/DirectScopePropagationPolicy.php) when only the immediate scope must participate in a
permission check.

### `can()`

Returns `true` when the subject has at least one of the requested permissions, or all of them when you pass `Junction::And`.

- `$subject`: `Subject` — the subject being evaluated.
- `$permissions`: `array<string>` — the permission codes to evaluate. Always an array, never a bare string.
- `$junction`: `Junction` (default: `Junction::Or`) — the combinator.

### `cannot()`

Inverse of `can()`. Same signature.

### `is()`

Returns `true` when the subject has at least one of the requested roles, or all of them when you pass `Junction::And`.

- `$subject`: `Subject` — the subject being evaluated.
- `$roles`: `array<string>` — the role codes to evaluate.
- `$junction`: `Junction` (default: `Junction::Or`) — the combinator.

### `isnt()`

Inverse of `is()`. Same signature.

### `Junction`

Enum with two cases:

- `Junction::Or` (default) — one match is enough.
- `Junction::And` — every code must be allowed.

## Operators

The three operators mutate state through your repositories. All of them are variadic, so they can attach or detach multiple items in a
single call.

### Granter

[`Granter`](src/Operators/Granter.php) grants assignments.

Its constructor requires a [`ScopeBoundary`](src/Authorization/ScopeBoundary.php) after the repositories. Sentinel uses it to validate the
requested permissions before mutating
an authorization relationship.

```php
$granter->grant($user, $admin);                  // user has the admin role
$granter->grant($admin, $edit, $delete);         // admin role has two permissions
$granter->grant($user, $admin, $edit, $delete);  // everything in one call
```

It accepts `Subject` or `Role` as owner. If it receives a `Role` as owner and another `Role` as authorization, it throws [
`InvalidAuthorization`](src/Errors/InvalidAuthorization.php): roles cannot contain other roles.

### Denier

[`Denier`](src/Operators/Denier.php) explicitly denies permissions to a subject. It applies the central rule: a direct denial overrides any
inherited permission.

```php
$denier->deny($user, $edit);
$denier->deny($user, $edit, $delete);   // variadic
```

It only accepts `Subject` as an owner. Roles do not support denials.

### Revoker

[`Revoker`](src/Operators/Revoker.php) removes any previous assignment, whether it was a grant or a denial. It is idempotent: if the
assignment does not exist, it makes no changes.

```php
$revoker->revoke($user, $admin);
$revoker->revoke($user, $edit, $delete);
$revoker->purge($user);                         // remove every role and direct permission relation
```

`revoke()` accepts `Subject` or `Role` as owner. `purge()` accepts only a `Subject` and removes every role assignment, direct permission,
and explicit denial for that subject. It does not remove the subject or the global role and permission definitions.

## Registry

[`PermissionRegistry`](src/Registry/PermissionRegistry.php) and [`RoleRegistry`](src/Registry/RoleRegistry.php) manage the registration
operations for each entity: `create`, `update`, `remove`, plus read helpers for administration (`lookup`, `find`).

```php
$roleRegistry       = new RoleRegistry($roleRepository, $subjectRoleRepository);
$permissionRegistry = new PermissionRegistry($permissionRepository, $subjectPermissionRepository, $rolePermissionRepository);

$admin = $roleRegistry->create('admin', 'Administrator');
$roleRegistry->update($admin->id(), 'Administrator', 'Full access');
$roleRegistry->remove($admin->id());
```

- `create()` returns the entity with its assigned id. It throws `*AlreadyExists` when the code already exists. `RoleRegistry::create()`
  accepts an optional scope.
- `update()` operates by id. Passing `null` as description clears it.
- `remove()` is idempotent: if the id does not exist, it makes no changes. If the entity is in use, it throws `*InUse`.
- `lookup()` returns the typed collection of entities whose codes match. `RoleRegistry::lookup()` additionally requires
  `Subject|null $scope` as its first argument and resolves roles for that exact scope.
- `find()` returns the entity matching the code, or `null` when none exists. `RoleRegistry::find()` additionally requires
  `Subject|null $scope`.

## Errors

All exceptions thrown by Sentinel extend [`AuthorizationError`](src/Errors/AuthorizationError.php), which itself extends `RuntimeException`.
To catch any Sentinel error:

```php
try {
    $roleRegistry->create('admin', 'Administrator');
} catch (AuthorizationError $e) {
    // covers PermissionAlreadyExists, RoleNotFound, PermissionInUse, etc.
}
```

Specific exceptions:

| Exception                 | Thrown when                                                                                                        |
|---------------------------|--------------------------------------------------------------------------------------------------------------------|
| `PermissionAlreadyExists` | `PermissionRegistry::create` receives a code that already exists.                                                  |
| `RoleAlreadyExists`       | `RoleRegistry::create` receives a code that already exists.                                                        |
| `PermissionNotFound`      | `PermissionRegistry::update` or an operator cannot find the id/code.                                               |
| `RoleNotFound`            | `RoleRegistry::update` or an operator cannot find the id/code.                                                     |
| `PermissionInUse`         | `PermissionRegistry::remove` detects a linked subject or role.                                                     |
| `RoleInUse`               | `RoleRegistry::remove` detects a subject linked to that role.                                                      |
| `InvalidAuthorization`    | `Granter` receives a role for a role owner, an incompatible scoped role, or permissions outside the owner's scope. |
| `ScopeCycleDetected`      | A scope propagation policy detects a repeated subject.                                                             |

## Development

```bash
make composer-install
make test
```

## Additional documentation

You can find more details in the source code as well as in the tests located in [`tests/`](tests).

The tests cover different usage scenarios and can serve as additional reference for understanding the library’s behavior.
