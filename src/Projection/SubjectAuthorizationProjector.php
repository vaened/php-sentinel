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

final readonly class SubjectAuthorizationProjector
{
    public function __construct(
        protected SubjectRoleRepository $roles,
        protected SubjectPermissionRepository $permissions,
    ) {
    }

    public function project(Subject $subject): SubjectAuthorizationProjection
    {
        $roles = $this->roles->allOf($subject)->codes();
        $permissions = [];

        foreach ($this->permissions->allOf($subject) as $permission) {
            $permissions[$permission->code()] = !$permission->isDenied();
        }

        foreach ($this->roles->grants($subject) as $permission) {
            $permissions[$permission->code()] ??= true;
        }

        return new SubjectAuthorizationProjection($roles, $permissions);
    }
}
