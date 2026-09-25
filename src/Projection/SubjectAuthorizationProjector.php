<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Projection;

use Vaened\Sentinel\Authorizations;
use Vaened\Sentinel\Repositories\SubjectPermissionRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;
use Vaened\Sentinel\Subject;
use Vaened\Sentinel\SubjectPermissions;
use Vaened\Sentinel\SubjectPermissionState;

final readonly class SubjectAuthorizationProjector
{
    public function __construct(
        protected SubjectRoleRepository       $roles,
        protected SubjectPermissionRepository $permissions,
    )
    {
    }

    public function project(Subject $subject): SubjectAuthorizationProjection
    {
        $subjectPermissions = $this->permissions->allOf($subject);
        $roles              = new Authorizations(array_map(
            static fn($role): ProjectionAuthorization => new ProjectionAuthorization($role->code()),
            $this->roles->allOf($subject)->values(),
        ));
        $permissions        = $subjectPermissions->values();

        foreach ($this->roles->grants($subject) as $permission) {
            foreach ($permissions as $index => $subjectPermission) {
                if ($subjectPermission->code() === $permission->code()) {
                    $permissions[$index] = new ProjectionSubjectPermission(
                        $permission->code(),
                        $subjectPermission->state()->withInherited(),
                    );

                    continue 2;
                }
            }

            $permissions[] = new ProjectionSubjectPermission($permission->code(), SubjectPermissionState::Inherited);
        }

        return new SubjectAuthorizationProjection($roles, new SubjectPermissions($permissions));
    }
}
