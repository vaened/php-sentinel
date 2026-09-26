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

use Vaened\Sentinel\Operators\SubjectPermissionSnapshot;
use Vaened\Sentinel\Projection\ProjectionSubjectPermission;
use Vaened\Sentinel\Repositories\SubjectPermissionRepository as SubjectPermissionRepositoryContract;
use Vaened\Sentinel\Subject;
use Vaened\Sentinel\SubjectPermissions;
use Vaened\Sentinel\SubjectPermissionState;

final readonly class CachedSubjectPermissionRepository implements SubjectPermissionRepositoryContract
{
    public function __construct(
        private SubjectPermissionRepositoryContract $repository,
        private SubjectAuthorizationProjectionCache $projections,
    )
    {
    }

    public function lookup(Subject $subject, string ...$codes): SubjectPermissions
    {
        if (empty($codes)) {
            return new SubjectPermissions([]);
        }

        return $this->owned($this->projections->loadOrBuild($subject)->permissionsOf($codes));
    }

    public function exists(int|string $permissionId): bool
    {
        return $this->repository->exists($permissionId);
    }

    public function allOf(Subject $subject): SubjectPermissions
    {
        return $this->owned($this->projections->loadOrBuild($subject)->permissions());
    }

    public function create(Subject $subject, SubjectPermissionSnapshot ...$permissions): void
    {
        $this->repository->create($subject, ...$permissions);
        $this->projections->forget($subject);
    }

    public function update(Subject $subject, SubjectPermissionSnapshot ...$permissions): void
    {
        $this->repository->update($subject, ...$permissions);
        $this->projections->forget($subject);
    }

    public function remove(Subject $subject, SubjectPermissionSnapshot ...$permissions): void
    {
        $this->repository->remove($subject, ...$permissions);
        $this->projections->forget($subject);
    }

    public function purge(Subject $subject): void
    {
        $this->repository->purge($subject);
        $this->projections->forget($subject);
    }

    private function owned(SubjectPermissions $permissions): SubjectPermissions
    {
        $owned = [];

        foreach ($permissions as $permission) {
            if (!$permission->state()->isOwned()) {
                continue;
            }

            $owned[] = new ProjectionSubjectPermission(
                $permission->code(),
                $permission->state()->isDenied()
                    ? SubjectPermissionState::Denied
                    : SubjectPermissionState::Direct,
            );
        }

        return new SubjectPermissions($owned);
    }
}
