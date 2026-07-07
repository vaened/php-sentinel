# PHP Sentinel

Framework-agnostic authorization core for PHP 8.4+.

## Installation

```bash
composer require vaened/php-sentinel
```

Requires PHP 8.4 or higher. Its only dependency is `vaened/support`.

## Quick start

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
        - `Identifier`: [`Identifier`](src/Identifier.php)

- **Authorization**
    - Contract: [`Authorization`](src/Authorization.php)
    - Defines:
        - `id(): int|string`
        - `code(): string`
        - `name(): string`
        - `description(): ?string`
    - `Role` and `Permission` expose scalar identity.
    - Derived contracts:
        - **Role**
            - Contract: [`Role`](src/Role.php)
            - Represents a composite authorization. A role groups permissions.
        - **Permission**
            - Contract: [`Permission`](src/Permission.php)
            - Represents an atomic authorization.

### Repositories

Repositories persist both the catalog and the relationships between subjects, roles, and permissions.

- **RoleRepository**
  - Contract: [`RoleRepository`](src/Repositories/RoleRepository.php)
  - Stores role records with `id`, `code`, `name`, and `description`.

- **PermissionRepository**
  - Contract: [`PermissionRepository`](src/Repositories/PermissionRepository.php)
  - Stores permission records with `id`, `code`, `name`, and `description`.

- **SubjectRoleRepository**
    - Contract: [`SubjectRoleRepository`](src/Repositories/SubjectRoleRepository.php)
    - Persists `subject ↔ role` links.

- **SubjectPermissionRepository**
    - Contract: [`SubjectPermissionRepository`](src/Repositories/SubjectPermissionRepository.php)
    - Persists `subject ↔ permission` links.
    - Each assignment exposes `isDenied()`.

- **RolePermissionRepository**
    - Contract: [`RolePermissionRepository`](src/Repositories/RolePermissionRepository.php)
    - Persists `role ↔ permission` links.
    - Roles only grant permissions; they do not support explicit denials.

Each repository exposes the combination of `lookup`, `grants`, `exists`, `allOf`, `create`, `update`, and `remove` that belongs to its
own contract.

### Entry providers

The entry providers are concrete core services. They compose repositories and return the entries consumed by the `Authorizer`.

- **PermissionEntryProvider**
    - Service: [`PermissionEntryProvider`](src/Authorization/PermissionEntryProvider.php)
    - Encodes the effective precedence of permissions.
    - Invariants:
        - it only returns codes that were requested;
        - for a subject, direct permissions are resolved first and missing codes are completed through role grants;
        - **deny overrides inherited grant**: a direct denial on the subject overrides any permission inherited from a role;
        - for a role, effective permissions are the role's direct permissions.

- **RoleEntryProvider**
    - Service: [`RoleEntryProvider`](src/Authorization/RoleEntryProvider.php)
    - Resolves which requested role codes are effectively assigned to the subject.
    - It does not define precedence rules.

## Implementation references

- Reference wiring: [`tests/Integration/AuthorizerFlowTest.php`](tests/Integration/AuthorizerFlowTest.php)
- Reference in-memory implementations: [`tests/Runtime/`](tests/Runtime)
- In-memory repositories: [`tests/Runtime/Repositories/`](tests/Runtime/Repositories)
- Core `PermissionEntryProvider`: [`src/Authorization/PermissionEntryProvider.php`](src/Authorization/PermissionEntryProvider.php)
- Core `RoleEntryProvider`: [`src/Authorization/RoleEntryProvider.php`](src/Authorization/RoleEntryProvider.php)

## Authorizer

[`Authorizer`](src/Authorization/Authorizer.php) is the read gate. It combines a `PermissionEntryProvider` and a `RoleEntryProvider` and
answers boolean questions about a `Subject` or a `Role`. It is constructed once with both providers and is then ready to answer `can`,
`cannot`, `is`, and `isnt` at any time.

### `can()`

Returns `true` when the owner has at least one of the requested permissions, or all of them when you pass `Junction::And`.

- `$owner`: `Subject|Role` — the entity being evaluated.
- `$permissions`: `array<string>` — the permission codes to evaluate. Always an array, never a bare string.
- `$junction`: `Junction` (default: `Junction::Or`) — the combinator.

### `cannot()`

Inverse of `can()`. Same signature.

### `is()`

Returns `true` when the subject has at least one of the requested roles, or all of them when you pass `Junction::And`. Unlike `can`, it only
accepts `Subject` as an owner, not `Role`.

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
```

It accepts `Subject` or `Role` as owner.

## Registry

[`PermissionRegistry`](src/Registry/PermissionRegistry.php) and [`RoleRegistry`](src/Registry/RoleRegistry.php) manage the registration
operations for each entity: `create`, `update`, and `remove`.

```php
$roleRegistry       = new RoleRegistry($roleRepository, $subjectRoleRepository);
$permissionRegistry = new PermissionRegistry($permissionRepository, $subjectPermissionRepository, $rolePermissionRepository);

$admin = $roleRegistry->create('admin', 'Administrator');
$roleRegistry->update($admin->id(), 'Administrator', 'Full access');
$roleRegistry->remove($admin->id());
```

- `create()` returns the entity with its assigned id. It throws `*AlreadyExists` when the code already exists.
- `update()` operates by id. Passing `null` as description clears it.
- `remove()` is idempotent: if the id does not exist, it makes no changes. If the entity is in use, it throws `*InUse`.

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

| Exception                 | Thrown when                                                            |
|---------------------------|------------------------------------------------------------------------|
| `PermissionAlreadyExists` | `PermissionRegistry::create` receives a code that already exists.      |
| `RoleAlreadyExists`       | `RoleRegistry::create` receives a code that already exists.            |
| `PermissionNotFound`      | `PermissionRegistry::update` or an operator cannot find the id/code.   |
| `RoleNotFound`            | `RoleRegistry::update` or an operator cannot find the id/code.         |
| `PermissionInUse`         | `PermissionRegistry::remove` detects a linked subject or role.         |
| `RoleInUse`               | `RoleRegistry::remove` detects a subject linked to that role.          |
| `InvalidAuthorization`    | `Granter` receives roles as targets from an owner that is also a role. |

## Development

```bash
make composer-install
make test
```

## License

MIT — see [LICENSE](LICENSE).
