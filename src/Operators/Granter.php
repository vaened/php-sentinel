<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Operators;

use Vaened\Sentinel\Authorization;
use Vaened\Sentinel\Authorization\Authorizer;
use Vaened\Sentinel\Authorization\Junction;
use Vaened\Sentinel\Errors\InvalidAuthorization;
use Vaened\Sentinel\Permission;
use Vaened\Sentinel\Permissions;
use Vaened\Sentinel\Repositories\PermissionRepository;
use Vaened\Sentinel\Repositories\RolePermissionRepository;
use Vaened\Sentinel\Repositories\RoleRepository;
use Vaened\Sentinel\Repositories\SubjectPermissionRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;
use Vaened\Sentinel\Role;
use Vaened\Sentinel\Roles;
use Vaened\Sentinel\Scopes;
use Vaened\Sentinel\Subject;

final readonly class Granter extends Operator
{
    use BindingOperator;

    public function __construct(
        RoleRepository                        $roles,
        PermissionRepository                  $permissions,
        protected SubjectRoleRepository       $subjectRoles,
        protected SubjectPermissionRepository $subjectPermissions,
        protected RolePermissionRepository    $rolePermissions,
        protected Authorizer                  $authorizer,
    )
    {
        parent::__construct($roles, $permissions);
    }

    public function grant(Subject|Role $owner, Authorization ...$authorizations): void
    {
        [$roles, $permissions] = $this->split(...$authorizations);

        $roles       = $roles->isEmpty() ? $roles : $this->takeRolesOrFail($roles);
        $permissions = $permissions->isEmpty() ? $permissions : $this->takePermissionsOrFail($permissions);

        $this->validateGrant($owner, $roles, $permissions);

        $this->bind($owner, $roles, $permissions);
    }

    protected function forRoles(Subject $owner, Roles $roles): void
    {
        $assigned = $this->subjectRoles->lookup($owner, ...$roles->codes());
        $toCreate = $roles->filter(static fn(Role $role): bool => !$assigned->hasCode($role->code()));

        if ($toCreate->isEmpty()) {
            return;
        }

        $this->subjectRoles->create($owner, ...$toCreate->values());
    }

    protected function forSubjectPermissions(Subject $owner, Permissions $permissions): void
    {
        $assigned  = $this->subjectPermissions->lookup($owner, ...$permissions->codes());
        $inherited = null;

        $toCreate = [];
        $toUpdate = [];

        foreach ($permissions as $permission) {
            $assignment = $assigned->find($permission->code());

            if (null === $assignment) {
                $inherited ??= $this->subjectRoles->grants($owner, $permissions->codes())->codes();

                if (in_array($permission->code(), $inherited, true)) {
                    continue;
                }

                $toCreate[] = SubjectPermissionSnapshot::from($permission);
                continue;
            }

            if ($assignment->state()->isDenied()) {
                $toUpdate[] = SubjectPermissionSnapshot::from($permission);
            }
        }

        if (!empty($toCreate)) {
            $this->subjectPermissions->create($owner, ...$toCreate);
        }

        if (!empty($toUpdate)) {
            $this->subjectPermissions->update($owner, ...$toUpdate);
        }
    }

    protected function forRolePermissions(Role $owner, Permissions $permissions): void
    {
        $assigned = $this->rolePermissions->lookup($owner, ...$permissions->codes());
        $toCreate = $permissions->filter(static fn(Permission $permission): bool => !$assigned->hasCode($permission->code()));

        if ($toCreate->isEmpty()) {
            return;
        }

        $this->rolePermissions->create($owner, ...$toCreate->values());
    }

    private function validateGrant(Subject|Role $owner, Roles $roles, Permissions $permissions): void
    {
        if ($owner instanceof Role) {
            $this->ensureScopeAllows($owner, $permissions->codes());

            return;
        }

        $this->ensureScopesMatch($owner, $roles);
        $this->ensureRolesFitScope($owner, $roles);
        $this->ensureScopeAllows($owner, $permissions->codes());
    }

    private function ensureScopesMatch(Subject $subject, Roles $roles): void
    {
        foreach ($roles as $role) {
            if ($this->canAssign($subject, $role)) {
                continue;
            }

            throw InvalidAuthorization::forRoleScope($subject, $role, $role->scope());
        }
    }

    private function canAssign(Subject $subject, Role $role): bool
    {
        $roleScope = $role->scope();

        if ($roleScope === null) {
            return true;
        }

        return Scopes::same($subject->scope(), $roleScope);
    }

    private function ensureRolesFitScope(Subject $subject, Roles $roles): void
    {
        foreach ($roles as $role) {
            $this->ensureScopeAllows($subject, $this->rolePermissions->allOf($role)->codes());
        }
    }

    private function ensureScopeAllows(Subject|Role $owner, array $permissions): void
    {
        $scope = $owner->scope();

        if ($scope === null || empty($permissions)) {
            return;
        }

        if ($this->authorizer->can($scope, $permissions, Junction::And)) {
            return;
        }

        throw InvalidAuthorization::forScopePermissions($owner, $scope, $permissions);
    }
}
