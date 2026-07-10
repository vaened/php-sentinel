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

use Vaened\Sentinel\Cache\Authorizations\CachedSubjectPermission;
use Vaened\Sentinel\Operators\SubjectPermissionSnapshot;
use Vaened\Sentinel\Projection\SubjectAuthorizationProjection;
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

        $permissions = $this->projections->loadOrBuild($subject)->permissions();
        $matched     = array_intersect_key($permissions, array_flip($codes));

        return new SubjectPermissions(self::restore($matched));
    }

    public function exists(int|string $permissionId): bool
    {
        return $this->repository->exists($permissionId);
    }

    public function allOf(Subject $subject): SubjectPermissions
    {
        $permissions = $this->projections->loadOrBuild($subject)->permissions();

        return new SubjectPermissions(self::restore($permissions));
    }

    public function create(Subject $subject, SubjectPermissionSnapshot ...$permissions): void
    {
        $this->repository->create($subject, ...$permissions);
        $this->saveProjection($subject, ...$permissions);
    }

    public function update(Subject $subject, SubjectPermissionSnapshot ...$permissions): void
    {
        $this->repository->update($subject, ...$permissions);
        $this->saveProjection($subject, ...$permissions);
    }

    public function remove(Subject $subject, SubjectPermissionSnapshot ...$permissions): void
    {
        $this->repository->remove($subject, ...$permissions);
        $this->projections->forget($subject);
    }

    private function saveProjection(Subject $subject, SubjectPermissionSnapshot ...$permissions): void
    {
        $projection = $this->projections->loadOrBuild($subject);

        foreach ($permissions as $permission) {
            $projection = $this->withPermission($projection, $permission);
        }

        $this->projections->save($subject, $projection);
    }

    private function withPermission(
        SubjectAuthorizationProjection $projection,
        SubjectPermissionSnapshot      $permission,
    ): SubjectAuthorizationProjection
    {
        $permissions                      = $projection->permissions();
        $permissions[$permission->code()] = SubjectPermissionState::fromBoolean($permission->isDenied())->value;

        return new SubjectAuthorizationProjection($projection->roles(), $permissions);
    }

    private static function restore(array $permissions): array
    {
        return array_map(
            fn(string $code, int $state) => CachedSubjectPermission::from($code, SubjectPermissionState::from($state)),
            array_keys($permissions),
            array_values($permissions),
        );
    }
}
