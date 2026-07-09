<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Cache;

use Vaened\Sentinel\Authorizations;
use Vaened\Sentinel\Permission;
use Vaened\Sentinel\Repositories\RolePermissionRepository as RolePermissionRepositoryContract;
use Vaened\Sentinel\Role;

final readonly class CachedRolePermissionRepository implements RolePermissionRepositoryContract
{
    public function __construct(
        private RolePermissionRepositoryContract $repository,
        private AuthorizationCacheStore          $cache,
    )
    {
    }

    public function lookup(Role $role, string ...$codes): Authorizations
    {
        return $this->repository->lookup($role, ...$codes);
    }

    public function allOf(Role $role): Authorizations
    {
        return $this->repository->allOf($role);
    }

    public function exists(int|string $permissionId): bool
    {
        return $this->repository->exists($permissionId);
    }

    public function create(Role $role, Permission ...$permissions): void
    {
        $this->repository->create($role, ...$permissions);
        $this->cache->invalidate();
    }

    public function remove(Role $role, Permission ...$permissions): void
    {
        $this->repository->remove($role, ...$permissions);
        $this->cache->invalidate();
    }
}
