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

final readonly class Granter extends Operator
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

    public function grant(Subject|Role $owner, Authorization ...$authorizations): void
    {
        $this->bind($owner, ...$authorizations);
    }

    protected function forRoles(Subject $owner, Roles $roles): void
    {
        $available = $this->takeRolesOrFail($roles);
        $assigned  = $this->subjectRoles->lookup($owner, ...$roles->codes());
        $toCreate  = $available->filter(static fn(Role $role): bool => !$assigned->hasCode($role->code()));

        if ($toCreate->isEmpty()) {
            return;
        }

        $this->subjectRoles->create($owner, ...$toCreate->values());
    }

    protected function forSubjectPermissions(Subject $owner, Permissions $permissions): void
    {
        $available = $this->takePermissionsOrFail($permissions);
        $assigned  = $this->subjectPermissions->lookup($owner, ...$permissions->codes());

        $toCreate = [];
        $toUpdate = [];

        foreach ($available as $permission) {
            $assignment = $assigned->find($permission->code());

            if (null === $assignment) {
                $toCreate[] = new SubjectPermissionSnapshot($permission);
                continue;
            }

            if ($assignment->isDenied()) {
                $toUpdate[] = new SubjectPermissionSnapshot($assignment);
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
        $available = $this->takePermissionsOrFail($permissions);
        $assigned  = $this->rolePermissions->lookup($owner, ...$permissions->codes());
        $toCreate  = $available->filter(static fn(Permission $permission): bool => !$assigned->hasCode($permission->code()));

        if ($toCreate->isEmpty()) {
            return;
        }

        $this->rolePermissions->create($owner, ...$toCreate->values());
    }
}
