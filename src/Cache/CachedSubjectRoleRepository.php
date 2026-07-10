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
use Vaened\Sentinel\Repositories\RolePermissionRepository as RolePermissionRepositoryContract;
use Vaened\Sentinel\Repositories\SubjectRoleRepository as SubjectRoleRepositoryContract;
use Vaened\Sentinel\Role as RoleContract;
use Vaened\Sentinel\Subject;

final readonly class CachedSubjectRoleRepository implements SubjectRoleRepositoryContract
{
    public function __construct(
        private SubjectRoleRepositoryContract       $repository,
        private RolePermissionRepositoryContract    $rolePermissions,
        private SubjectAuthorizationProjectionCache $projections,
    )
    {
    }

    public function lookup(Subject $subject, string ...$codes): Authorizations
    {
        if (empty($codes)) {
            return new Authorizations([]);
        }

        return $this->projections->loadOrBuild($subject)->rolesOf($codes);
    }

    public function grants(Subject $subject, ?array $codes = null): Authorizations
    {
        return $this->repository->grants($subject, $codes);
    }

    public function exists(int|string $roleId): bool
    {
        return $this->repository->exists($roleId);
    }

    public function allOf(Subject $subject): Authorizations
    {
        return $this->projections->loadOrBuild($subject)->roles();
    }

    public function create(Subject $subject, RoleContract ...$roles): void
    {
        $this->repository->create($subject, ...$roles);

        $projection = $this->projections->loadOrBuild($subject);

        foreach ($roles as $role) {
            $effectiveCodes = $this->rolePermissions->allOf($role)->codes();
            $projection     = $projection->integrate($role, $effectiveCodes);
        }

        $this->projections->save($subject, $projection);
    }

    public function remove(Subject $subject, RoleContract ...$roles): void
    {
        $this->repository->remove($subject, ...$roles);
        $this->projections->forget($subject);
    }
}
