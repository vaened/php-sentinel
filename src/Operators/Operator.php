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

use Vaened\Sentinel\Authorizations;
use Vaened\Sentinel\Errors\PermissionNotFound;
use Vaened\Sentinel\Errors\RoleNotFound;
use Vaened\Sentinel\Permissions;
use Vaened\Sentinel\Repositories\PermissionRepository;
use Vaened\Sentinel\Repositories\RoleRepository;
use Vaened\Sentinel\Roles;

abstract readonly class Operator
{
    public function __construct(
        protected RoleRepository       $roles,
        protected PermissionRepository $permissions,
    )
    {
    }

    protected function takePermissionsOrFail(Permissions $permissions): Authorizations
    {
        $available = $this->permissions->lookup(...$permissions->codes());
        $missing   = $available->missing($permissions->codes());

        if (!empty($missing)) {
            throw PermissionNotFound::fromCodes($missing);
        }

        return $available;
    }

    protected function takeRolesOrFail(Roles $roles): Authorizations
    {
        $available = $this->roles->lookup(...$roles->codes());
        $missing   = $available->missing($roles->codes());

        if (!empty($missing)) {
            throw RoleNotFound::fromCodes($missing);
        }

        return $available;
    }
}
