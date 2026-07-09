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

use Vaened\Sentinel\Repositories\PermissionRepository;
use Vaened\Sentinel\Repositories\RolePermissionRepository;
use Vaened\Sentinel\Repositories\RoleRepository;
use Vaened\Sentinel\Repositories\SubjectPermissionRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;

/**
 * Result of {@see SentinelCacheFactory::build()}: the five cached repositories
 * that replace the base ones in the rest of the application.
 *
 * The cache store and projection cache are not exposed here because they are
 * internal to the cache layer. Consumers that need to invalidate manually
 * instantiate {@see AuthorizationCacheStore} directly with the same driver
 * and settings.
 */
final readonly class CachedRepositories
{
    public function __construct(
        private RoleRepository              $roleRepository,
        private PermissionRepository        $permissionRepository,
        private RolePermissionRepository    $rolePermissionRepository,
        private SubjectRoleRepository       $subjectRoleRepository,
        private SubjectPermissionRepository $subjectPermissionRepository,
    )
    {
    }

    public function roleRepository(): RoleRepository
    {
        return $this->roleRepository;
    }

    public function permissionRepository(): PermissionRepository
    {
        return $this->permissionRepository;
    }

    public function rolePermissionRepository(): RolePermissionRepository
    {
        return $this->rolePermissionRepository;
    }

    public function subjectRoleRepository(): SubjectRoleRepository
    {
        return $this->subjectRoleRepository;
    }

    public function subjectPermissionRepository(): SubjectPermissionRepository
    {
        return $this->subjectPermissionRepository;
    }
}