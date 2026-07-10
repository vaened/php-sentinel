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
use Vaened\Sentinel\Cache\Authorizations\CachedAuthorization;
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

        $projection = $this->projections->loadOrBuild($subject);
        $assigned   = array_values(array_intersect($projection->roles(), $codes));

        return new Authorizations(array_map(
            fn(string $code) => CachedAuthorization::from($code),
            $assigned,
        ));
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
        $projection = $this->projections->loadOrBuild($subject);

        return new Authorizations(array_map(
            fn(string $code) => CachedAuthorization::from($code),
            $projection->roles(),
        ));
    }

    public function create(Subject $subject, RoleContract ...$roles): void
    {
        $this->repository->create($subject, ...$roles);

        $projection = $this->projections->loadOrBuild($subject);

        foreach ($roles as $role) {
            $effectiveCodes = $this->rolePermissions->allOf($role)->codes();
            $projection     = $this->projections->withRoleAdded($projection, $role, $effectiveCodes);
        }

        $this->projections->save($subject, $projection);
    }

    public function remove(Subject $subject, RoleContract ...$roles): void
    {
        $this->repository->remove($subject, ...$roles);
        $this->projections->forget($subject);
    }
}
