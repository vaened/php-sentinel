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
use Vaened\Sentinel\Permission;
use Vaened\Sentinel\Permissions;
use Vaened\Sentinel\Repositories\PermissionRepository;
use Vaened\Sentinel\Repositories\RolePermissionRepository;
use Vaened\Sentinel\Repositories\RoleRepository;
use Vaened\Sentinel\Repositories\SubjectPermissionRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;
use Vaened\Sentinel\Role;
use Vaened\Sentinel\Roles;
use Vaened\Sentinel\Subject;

final readonly class Revoker extends Operator
{
    use BindingOperator;

    public function __construct(
        RoleRepository                        $roles,
        PermissionRepository                  $permissions,
        protected SubjectRoleRepository       $subjectRoles,
        protected SubjectPermissionRepository $subjectPermissions,
        protected RolePermissionRepository    $rolePermissions,
    )
    {
        parent::__construct($roles, $permissions);
    }

    public function revoke(Subject|Role $owner, Authorization ...$authorizations): void
    {
        $this->bind($owner, ...$authorizations);
    }

    protected function forRoles(Subject $owner, Roles $roles): void
    {
        $available = $this->takeRolesOrFail($roles);
        $assigned  = $this->subjectRoles->lookup($owner, ...$roles->codes());
        $toRemove  = $available->filter(static fn(Role $role): bool => $assigned->hasCode($role->code()));

        if ($toRemove->isEmpty()) {
            return;
        }

        $this->subjectRoles->remove($owner, ...$toRemove->values());
    }

    protected function forSubjectPermissions(Subject $owner, Permissions $permissions): void
    {
        $available = $this->takePermissionsOrFail($permissions);
        $assigned  = $this->subjectPermissions->lookup($owner, ...$permissions->codes());

        $toRemove = [];

        foreach ($available as $permission) {
            $assignment = $assigned->find($permission->code());

            if (null !== $assignment) {
                $toRemove[] = new SubjectPermissionSnapshot($assignment->permissionId(), $assignment->code(), $assignment->isDenied());
            }
        }

        if (empty($toRemove)) {
            return;
        }

        $this->subjectPermissions->remove($owner, ...$toRemove);
    }

    protected function forRolePermissions(Role $owner, Permissions $permissions): void
    {
        $available = $this->takePermissionsOrFail($permissions);
        $assigned  = $this->rolePermissions->lookup($owner, ...$permissions->codes());
        $toRemove  = $available->filter(static fn(Permission $permission): bool => $assigned->hasCode($permission->code()));

        if ($toRemove->isEmpty()) {
            return;
        }

        $this->rolePermissions->remove($owner, ...$toRemove->values());
    }
}
