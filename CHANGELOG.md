# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.11.0] - 2026-09-27

### Added

- `ScopeBoundary` for validating that every permission in a grant is allowed by each applicable scope.
- `NoPropagationPolicy` for authorization checks and grants that must ignore scopes entirely.

### Fixed

- `DirectScopePropagationPolicy` now applies the same direct-scope rule to grants: only the owner's immediate scope is evaluated, not its
  ancestors.

### Breaking

- `Granter` now requires a `ScopeBoundary` constructor dependency instead of an `Authorizer`.
- Custom `ScopePropagationPolicy` implementations must change `scopes(Subject $subject)` to `scopes(Scopeable $owner)`.
- Removed `SubjectAuthorizationProjection::integrate()` and `SubjectAuthorizationProjection::override()`; cached authorization
  projections are no longer updated incrementally after mutations.

[0.11.0]: https://github.com/vaened/php-sentinel/compare/v0.10.0...v0.11.0

## [0.10.0] - 2026-09-26

### Added

- Scoped authorization support. Subjects and roles can expose an optional scope, and `Authorizer::can()` evaluates permissions across
  the configured scope chain. Transitive propagation is the default; direct propagation is available when only the immediate scope must
  participate.
- Scope-cycle detection during transitive authorization checks.
- `RolePermissionRepository::grants(Role ...$roles)` for retrieving the deduplicated permissions granted by multiple roles in one
  operation.

### Changed

- `Granter` now validates scoped role and permission assignments before writing any relationship. A rejected request leaves every
  requested assignment unchanged.
- Cached subject-role and subject-permission mutations now forget the affected projection after persistence succeeds. The next lookup
  rebuilds it from the wrapped repositories instead of applying a partial in-memory patch.

### Breaking

- `Subject` and `Role` now extend `Scopeable`; implementations must provide `scope(): Subject|null`.
- `RoleRepository::lookup()` now requires `Subject|null $scope` as its first argument. `RoleRepository::create()` accepts an optional
  scope, and the contract adds `match(...$codes)`.
- `RoleRegistry::lookup()` and `RoleRegistry::find()` now require a scope argument.
- `RolePermissionRepository` implementations must add `grants(Role ...$roles): Permissions`.
- `CachedSubjectRoleRepository` no longer accepts a `RolePermissionRepository` constructor dependency.

[0.10.0]: https://github.com/vaened/php-sentinel/compare/v0.9.0...v0.10.0

## [0.9.0] - 2026-09-25

### Added

- `Revoker::purge()` to remove all role assignments, direct permissions, and explicit denials from a subject without removing the subject or
  the global role and permission definitions.

### Breaking

- `SubjectRoleRepository` and `SubjectPermissionRepository` now require a `purge(Subject $subject)` implementation.

[0.9.0]: https://github.com/vaened/php-sentinel/compare/v0.8.0...v0.9.0

## [0.8.0] - 2026-09-25

### Changed

- Cached subject authorization projections now retain both a subject assignment or denial and a role-inherited grant for the same
  permission code. This allows cached role grants to be resolved from the projection without changing authorization precedence.

### Fixed

- Cached `SubjectRoleRepository::grants()` now uses the subject authorization projection after it has been built, avoiding repeated
  persistence reads for inherited permission checks.

### Breaking

- Invalidate authorization caches during deployment. Existing projections do not retain inherited-grant provenance and cannot be used
  after upgrading.

[0.8.0]: https://github.com/vaened/php-sentinel/compare/v0.7.1...v0.8.0

## [0.7.1] - 2026-08-24

### Fixed

- Cached subject permission operations now distinguish role-inherited permissions from subject-owned assignments, matching the
  database-backed repository and preventing redundant direct grants or removal attempts for inherited permissions.
- Losing the cache version metadata no longer reuses the `v1` namespace, preventing stale authorization projections from becoming visible
  again.

[0.7.1]: https://github.com/vaened/php-sentinel/compare/v0.7.0...v0.7.1

## [0.7.0] - 2026-07-10

### Changed

- **Breaking:** `RoleRepository::lookup()` and `PermissionRepository::lookup()` now return the typed collections `Roles` and
  `Permissions` instead of the generic `Authorizations`. Implementations of those contracts must update their return type
  accordingly.
- The cached subject role and permission repositories now consume the typed collections directly.

### Added

- `RoleRegistry::lookup(array $codes): Roles` and `RoleRegistry::find(string $code): Role|null`.
- `PermissionRegistry::lookup(array $codes): Permissions` and `PermissionRegistry::find(string $code): Permission|null`.

[0.7.0]: https://github.com/vaened/php-sentinel/compare/v0.6.0...v0.7.0

## [0.6.0] - 2026-07-10

### Added

- `SubjectAuthorizationProjection::fromArray()` for restoring a validated typed projection from its flat cache payload.
- `ProjectionAuthorization` and `ProjectionSubjectPermission` as the projection-owned authorization entries.

### Changed

- `SubjectRoleRepository::grants()` now accepts `?array $codes = null` instead of variadic codes. `null` resolves every permission
  inherited through the subject's roles, an empty array resolves none, and a populated array remains a code filter.
- `SubjectAuthorizationProjection` now keeps typed `Authorizations` and `SubjectPermissions` in memory. Its constructor accepts those
  collections, while `toArray()` remains the flat serialization boundary for cache storage and external consumers.
- Projection filtering and mutation now live on `SubjectAuthorizationProjection`: `rolesOf()`, `permissionsOf()`, `integrate()`, and
  `override()` preserve the effective authorization state without exposing the cache payload to cached repositories.
- The PSR-16 store and cached subject repositories now consume typed projections directly instead of rebuilding authorization objects
  from primitive role and permission arrays.

### Removed

- `Cache\Authorizations\CachedAuthorization` and `Cache\Authorizations\CachedSubjectPermission`; their projection-specific
  replacements live under `Projection`.
- `SubjectAuthorizationProjectionCache::withRoleAdded()` and `bumpVersion()`. Role integration belongs to the projection, while global
  invalidation remains an `AuthorizationCacheStore` concern.

[0.6.0]: https://github.com/vaened/php-sentinel/compare/v0.5.0...v0.6.0

## [0.5.0] - 2026-07-09

### Added

- `SubjectPermissionState` enum with the explicit authorization states `Denied`, `Direct`, and `Inherited`, plus helper methods for
  mapping persisted booleans and resolving effective granted/owned semantics.
- Regression coverage for the cached `deny()` flow when a permission is inherited through a role.

### Changed

- `SubjectPermission` no longer exposes `isDenied(): bool`. It now exposes `state(): SubjectPermissionState` so implementations and
  adapters can distinguish direct assignments from inherited ones through a single contract.
- Subject-authorization projections and cached subject-permission adapters now preserve the full permission state instead of collapsing
  everything into a granted/denied boolean view.
- `Denier`, `Granter`, and the authorization-entry resolution flow now consume `SubjectPermissionState` directly when deciding how to
  interpret subject permissions.

### Fixed

- Denying a permission through `CachedSubjectPermissionRepository` now creates a direct subject denial when the permission only exists as
  an inherited role grant. Previously, the inherited cached permission could be mistaken for an existing direct assignment, so the denial
  was not persisted.

[0.5.0]: https://github.com/vaened/php-sentinel/compare/v0.4.1...v0.5.0

## [0.4.1] - 2026-07-09

### Fixed

- `RoleEntryProvider::for()` rejected `CachedAuthorization` instances returned by `CachedSubjectRoleRepository::lookup()` because
  the closure was typed against `Role`. The closure now accepts `Authorization`, which both `Role` and `CachedAuthorization`
  satisfy. This was a latent bug exposed when the cache layer started delivering slim read-only authorizations instead of full
  roles.

[0.4.1]: https://github.com/vaened/php-sentinel/compare/v0.4.0...v0.4.1

## [0.4.0] - 2026-07-09

### Added

- `psr/simple-cache: ^3.0` runtime dependency.
- `Vaened\Sentinel\Cache` namespace with the optional authorization-cache layer:
    - `AuthorizationCacheStore` interface.
    - `Stores\Psr16AuthorizationCacheStore` PSR-16 implementation.
    - `SubjectAuthorizationProjectionCache` for caching effective subject projections.
    - `CachedRepositories`, `CachedRoleRepository`, `CachedPermissionRepository`, `CachedRolePermissionRepository`,
      `CachedSubjectRoleRepository`, `CachedSubjectPermissionRepository` — read-through wrappers that replace the base
      repositories.
    - `SentinelCacheFactory` with `from()` and `as()` constructors and a `build()` method that returns `CachedRepositories`.
    - `Authorizations\CachedAuthorization` and `Authorizations\CachedSubjectPermission` — read-only implementations used to
      reconstruct projections.
    - `CacheSettings` value object with a `prefix` and an optional `ttl`. Default `ttl` is 12 hours
      (`CacheSettings::DEFAULT_TTL_IN_SECONDS`).

[0.4.0]: https://github.com/vaened/php-sentinel/compare/v0.3.1...v0.4.0

## [0.3.1] - 2026-07-08

### Changed

- `Authorizations` is no longer abstract. Provides `type(): string` returning `Authorization::class` directly. Implementations of the
  relation repositories can return `new Authorizations([...])` without wrapping in `Roles`/`Permissions`. Subclasses (`Roles`,
  `Permissions`, `SubjectPermissions`) are unchanged.

[0.3.1]: https://github.com/vaened/php-sentinel/compare/v0.3.0...v0.3.1

## [0.3.0] - 2026-07-08

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.0] - 2026-07-08

### Added

- `SubjectPermissionSnapshot::from(Permission)` factory method.
- Tests for the new repo contracts.

### Changed

- `Authorization` interface: `{ code(): string }`.
- `Role` and `Permission` interfaces now declare `id()`, `name()`, `description()` themselves.
- `SubjectPermission` interface: extends `Authorization`; methods `code()`, `isDenied()`.
- `SubjectPermissionSnapshot::__construct(int|string, string, bool)`.
- `SubjectRoleRepository`: `lookup`, `grants`, `allOf` return `Authorizations`.
- `RolePermissionRepository`: `lookup`, `allOf` return `Authorizations`.
- `RoleRepository::lookup` returns `Authorizations`.
- `SubjectPermissionRepository::create`, `update`, `remove` accept `SubjectPermissionSnapshot`.
- `Authorizer`, `PermissionEntryProvider`, `RoleEntryProvider`: `forSubject()` renamed to `for()`.

### Removed

- `Authorization::id()`, `Authorization::name()`, `Authorization::description()`.

## [0.2.0] - 2026-07-08

### Added

- `SubjectAuthorizationProjector` and `SubjectAuthorizationProjection` for exporting a subject's effective authorization state as flat role
  and permission data
- Unit coverage for the projection layer, including direct-permission precedence over inherited grants

### Changed

- `Authorizer::can()` and `Authorizer::cannot()` now evaluate permissions for `Subject` only
- `PermissionEntryProvider` now resolves subject permission entries only
- Repository contracts now expose `allOf(...)` where needed to support full-subject projection
- README updated to reflect the subject-only authorization flow

### Removed

- Role-based permission evaluation through `Authorizer::can()` and `Authorizer::cannot()`
- Role-specific permission-entry resolution path from `PermissionEntryProvider`

## [0.1.0] - 2026-07-07

### Added

- `Authorizer` with `can`, `cannot`, `is`, `isnt` and `Junction::And` / `Junction::Or` combinators
- `Granter`, `Denier`, `Revoker` operators for managing subject, role, and permission bindings
- `BindingOperator` trait that dispatches each operator to the correct repository based on the owner type
- `RoleRegistry` and `PermissionRegistry` for catalog `create`, `update`, and `remove`
- `PermissionEntryProvider` and `RoleEntryProvider` contracts with default in-memory implementations
- Type-safe collections: `Roles`, `Permissions`, `Authorizations`, `SubjectPermissions`
- Repository contracts: `RoleRepository`, `PermissionRepository`, `SubjectRoleRepository`, `SubjectPermissionRepository`,
  `RolePermissionRepository`
- `deny-overrides-grant` precedence rule: a subject-level denial overrides any permission inherited from a role
- Error hierarchy rooted at `AuthorizationError`: `PermissionAlreadyExists`, `RoleAlreadyExists`, `PermissionNotFound`, `RoleNotFound`,
  `PermissionInUse`, `RoleInUse`, `InvalidAuthorization`
- 78 tests covering integration, contract, and unit layers

[0.1.0]: https://github.com/vaened/php-sentinel/releases/tag/v0.1.0

[0.2.0]: https://github.com/vaened/php-sentinel/compare/v0.1.0...v0.2.0

[0.3.0]: https://github.com/vaened/php-sentinel/compare/v0.2.0...v0.3.0
