# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
- `Authorizations` is no longer abstract. Provides `type(): string` returning `Authorization::class` directly. Implementations of the relation repositories can return `new Authorizations([...])` without wrapping in `Roles`/`Permissions`. Subclasses (`Roles`, `Permissions`, `SubjectPermissions`) are unchanged.

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