<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Repositories;

use Vaened\Sentinel\Permission;
use Vaened\Sentinel\Permissions;
use Vaened\Sentinel\Role;

interface RolePermissionRepository
{
    public function lookup(Role $role, string ...$codes): Permissions;

    public function exists(int|string $permissionId): bool;

    public function create(Role $role, Permission ...$permissions): void;

    public function remove(Role $role, Permission ...$permissions): void;
}
