<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Tests\Runtime\Repositories;

use Vaened\Sentinel\Permission;
use Vaened\Sentinel\Permissions;
use Vaened\Sentinel\Repositories\RolePermissionRepository;
use Vaened\Sentinel\Role;

final class InMemoryRolePermissionRepository implements RolePermissionRepository
{
    /**
     * @var array<int|string, array<string, Permission>>
     */
    protected array $items = [];

    public function lookup(Role $role, string ...$codes): Permissions
    {
        $assigned = $this->items[$role->id()] ?? [];
        $codes    = array_flip($codes);

        return new Permissions(array_values(array_filter(
            $assigned,
            static fn(Permission $permission): bool => isset($codes[$permission->code()]),
        )));
    }

    public function exists(int|string $permissionId): bool
    {
        return array_any($this->items, fn($permissions) => array_any($permissions, fn($permission) => $permission->id() === $permissionId));
    }

    public function allOf(Role $role): Permissions
    {
        return new Permissions(array_values($this->items[$role->id()] ?? []));
    }

    public function create(Role $role, Permission ...$permissions): void
    {
        foreach ($permissions as $permission) {
            $this->items[$role->id()][$permission->code()] = $permission;
        }
    }

    public function remove(Role $role, Permission ...$permissions): void
    {
        foreach ($permissions as $permission) {
            unset($this->items[$role->id()][$permission->code()]);
        }
    }
}
