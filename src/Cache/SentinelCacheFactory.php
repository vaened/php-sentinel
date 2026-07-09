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

use Psr\SimpleCache\CacheInterface;
use Vaened\Sentinel\Repositories\PermissionRepository;
use Vaened\Sentinel\Repositories\RolePermissionRepository;
use Vaened\Sentinel\Repositories\RoleRepository;
use Vaened\Sentinel\Repositories\SubjectPermissionRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;

final readonly class SentinelCacheFactory
{
    public function __construct(
        private CacheInterface $driver,
        private CacheSettings  $settings,
    )
    {
    }

    public function build(
        RoleRepository              $roles,
        PermissionRepository        $permissions,
        RolePermissionRepository    $rolePermissions,
        SubjectRoleRepository       $subjectRoles,
        SubjectPermissionRepository $subjectPermissions,
    ): CachedRepositories
    {
        $store       = new AuthorizationCacheStore($this->driver, $this->settings);
        $projections = new SubjectAuthorizationProjectionCache($store, $subjectRoles, $subjectPermissions);

        return new CachedRepositories(
            roleRepository             : new CachedRoleRepository($roles, $store),
            permissionRepository       : new CachedPermissionRepository($permissions, $store),
            rolePermissionRepository   : new CachedRolePermissionRepository($rolePermissions, $store),
            subjectRoleRepository      : new CachedSubjectRoleRepository($subjectRoles, $rolePermissions, $projections),
            subjectPermissionRepository: new CachedSubjectPermissionRepository($subjectPermissions, $projections),
        );
    }
}