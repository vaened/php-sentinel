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
use Vaened\Sentinel\Cache\Stores\Psr16AuthorizationCacheStore;
use Vaened\Sentinel\Repositories\PermissionRepository;
use Vaened\Sentinel\Repositories\RolePermissionRepository;
use Vaened\Sentinel\Repositories\RoleRepository;
use Vaened\Sentinel\Repositories\SubjectPermissionRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;

final readonly class SentinelCacheFactory
{
    private function __construct(
        private AuthorizationCacheStore $store,
    )
    {
    }

    public static function from(CacheInterface $driver, CacheSettings $settings): self
    {
        return new self(new Psr16AuthorizationCacheStore($driver, $settings));
    }

    public static function as(AuthorizationCacheStore $store): self
    {
        return new self($store);
    }

    public function build(
        RoleRepository              $roles,
        PermissionRepository        $permissions,
        RolePermissionRepository    $rolePermissions,
        SubjectRoleRepository       $subjectRoles,
        SubjectPermissionRepository $subjectPermissions,
    ): CachedRepositories
    {
        $projections = new SubjectAuthorizationProjectionCache($this->store, $subjectRoles, $subjectPermissions);

        return new CachedRepositories(
            roleRepository             : new CachedRoleRepository($roles, $this->store),
            permissionRepository       : new CachedPermissionRepository($permissions, $this->store),
            rolePermissionRepository   : new CachedRolePermissionRepository($rolePermissions, $this->store),
            subjectRoleRepository      : new CachedSubjectRoleRepository($subjectRoles, $rolePermissions, $projections),
            subjectPermissionRepository: new CachedSubjectPermissionRepository($subjectPermissions, $projections),
        );
    }
}
