<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Tests\Runtime;

use Vaened\Sentinel\Authorization\PermissionEntryProvider;
use Vaened\Sentinel\Authorization\PermissionEntries;
use Vaened\Sentinel\Authorization\PermissionEntry;
use Vaened\Sentinel\Permission;
use Vaened\Sentinel\Role;
use Vaened\Sentinel\Repositories\RolePermissionRepository;
use Vaened\Sentinel\Subject;
use Vaened\Sentinel\Repositories\SubjectPermissionRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;

final readonly class InMemoryPermissionEntryProvider implements PermissionEntryProvider
{
    public function __construct(
        protected SubjectPermissionRepository $subjectPermissions,
        protected SubjectRoleRepository $subjectRoles,
        protected RolePermissionRepository $rolePermissions,
    ) {
    }

    public function forSubject(Subject $subject, string ...$permissions): PermissionEntries
    {
        $entries = [];
        $direct = $this->subjectPermissions->lookup($subject, ...$permissions);

        foreach ($permissions as $code) {
            $permission = $direct->find($code);

            if ($permission !== null) {
                $entries[] = new PermissionEntry($code, $permission->isDenied());
                continue;
            }

            foreach ($this->subjectRoles->allOf($subject) as $role) {
                if (!$this->rolePermissions->lookup($role, $code)->isEmpty()) {
                    $entries[] = new PermissionEntry($code);
                    break;
                }
            }
        }

        return new PermissionEntries($entries);
    }

    public function forRole(Role $role, string ...$permissions): PermissionEntries
    {
        return new PermissionEntries(array_map(
            static fn(Permission $permission): PermissionEntry => new PermissionEntry($permission->code()),
            $this->rolePermissions->lookup($role, ...$permissions)->values(),
        ));
    }
}
