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

use Vaened\Sentinel\Permission;
use Vaened\Sentinel\Permissions;
use Vaened\Sentinel\Repositories\PermissionRepository;
use Vaened\Sentinel\Repositories\RoleRepository;
use Vaened\Sentinel\Repositories\SubjectPermissionRepository;
use Vaened\Sentinel\Subject;

final readonly class Denier extends Operator
{
    public function __construct(
        RoleRepository                        $roles,
        PermissionRepository                  $permissions,
        protected SubjectPermissionRepository $permissionsOfSubjects,
    )
    {
        parent::__construct($roles, $permissions);
    }

    public function deny(Subject $owner, Permission ...$permissions): void
    {
        if (empty($permissions)) {
            return;
        }

        $permissions = new Permissions($permissions);
        $available   = $this->takePermissionsOrFail($permissions);
        $assigned    = $this->permissionsOfSubjects->lookup($owner, ...$permissions->codes());

        $toCreate = [];
        $toUpdate = [];

        foreach ($available as $permission) {
            $assignment = $assigned->find($permission->code());

            if (null === $assignment) {
                $toCreate[] = SubjectPermissionSnapshot::from($permission, true);
                continue;
            }

            if (!$assignment->isDenied()) {
                $toUpdate[] = new SubjectPermissionSnapshot($assignment->permissionId(), $assignment->code(), true);
            }
        }

        if (!empty($toCreate)) {
            $this->permissionsOfSubjects->create($owner, ...$toCreate);
        }

        if (!empty($toUpdate)) {
            $this->permissionsOfSubjects->update($owner, ...$toUpdate);
        }
    }
}
