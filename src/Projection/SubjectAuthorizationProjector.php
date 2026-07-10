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

use Vaened\Sentinel\Repositories\SubjectPermissionRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;
use Vaened\Sentinel\Subject;
use Vaened\Sentinel\Authorizations;
use Vaened\Sentinel\SubjectPermissions;
use Vaened\Sentinel\SubjectPermissionState;

final readonly class SubjectAuthorizationProjector
{
    public function __construct(
        protected SubjectRoleRepository $roles,
        protected SubjectPermissionRepository $permissions,
    ) {
    }

    public function project(Subject $subject): SubjectAuthorizationProjection
    {
        $subjectPermissions = $this->permissions->allOf($subject);
        $roles              = new Authorizations(array_map(
            static fn($role): ProjectionAuthorization => new ProjectionAuthorization($role->code()),
            $this->roles->allOf($subject)->values(),
        ));
        $permissions = $subjectPermissions->values();
        $known       = $subjectPermissions->codes();

        foreach ($this->roles->grants($subject) as $permission) {
            if (!in_array($permission->code(), $known, true)) {
                $permissions[] = new ProjectionSubjectPermission($permission->code(), SubjectPermissionState::Inherited);
                $known[]       = $permission->code();
            }
        }

        return new SubjectAuthorizationProjection($roles, new SubjectPermissions($permissions));
    }
}
