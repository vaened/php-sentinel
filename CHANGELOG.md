# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-07-07

### Added
- `Authorizer` with `can`, `cannot`, `is`, `isnt` and `Junction::And` / `Junction::Or` combinators
- `Granter`, `Denier`, `Revoker` operators for managing subject, role, and permission bindings
- `BindingOperator` trait that dispatches each operator to the correct repository based on the owner type
- `RoleRegistry` and `PermissionRegistry` for catalog `create`, `update`, and `remove`
- `PermissionEntryProvider` and `RoleEntryProvider` contracts with default in-memory implementations
- Type-safe collections: `Roles`, `Permissions`, `Authorizations`, `SubjectPermissions`
- Repository contracts: `RoleRepository`, `PermissionRepository`, `SubjectRoleRepository`, `SubjectPermissionRepository`, `RolePermissionRepository`
- `deny-overrides-grant` precedence rule: a subject-level denial overrides any permission inherited from a role
- Error hierarchy rooted at `AuthorizationError`: `PermissionAlreadyExists`, `RoleAlreadyExists`, `PermissionNotFound`, `RoleNotFound`, `PermissionInUse`, `RoleInUse`, `InvalidAuthorization`
- 78 tests covering integration, contract, and unit layers

[0.1.0]: https://github.com/vaened/php-sentinel/releases/tag/v0.1.0