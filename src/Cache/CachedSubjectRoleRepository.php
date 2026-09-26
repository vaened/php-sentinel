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
use Vaened\Sentinel\Repositories\SubjectRoleRepository as SubjectRoleRepositoryContract;
use Vaened\Sentinel\Role as RoleContract;
use Vaened\Sentinel\Subject;
use Vaened\Sentinel\SubjectPermission;

final readonly class CachedSubjectRoleRepository implements SubjectRoleRepositoryContract
{
    public function __construct(
        private SubjectRoleRepositoryContract       $repository,
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
        if ($codes === []) {
            return new Authorizations([]);
        }

        return new Authorizations(array_values(array_filter(
            $this->projections->loadOrBuild($subject)->permissions()->values(),
            static fn(SubjectPermission $permission): bool => $permission->state()->isInherited()
                && ($codes === null || in_array($permission->code(), $codes, true)),
        )));
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
        $this->projections->forget($subject);
    }

    public function remove(Subject $subject, RoleContract ...$roles): void
    {
        $this->repository->remove($subject, ...$roles);
        $this->projections->forget($subject);
    }

    public function purge(Subject $subject): void
    {
        $this->repository->purge($subject);
        $this->projections->forget($subject);
    }
}
