<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Tests\Unit\Cache;

use Vaened\Sentinel\Cache\CachedSubjectPermissionRepository;
use Vaened\Sentinel\Operators\Revoker;
use Vaened\Sentinel\Permission;
use Vaened\Sentinel\Permissions;
use Vaened\Sentinel\Projection\ProjectionSubjectPermission;
use Vaened\Sentinel\Repositories\PermissionRepository;
use Vaened\Sentinel\Repositories\RolePermissionRepository;
use Vaened\Sentinel\Repositories\RoleRepository;
use Vaened\Sentinel\Repositories\SubjectPermissionRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;
use Vaened\Sentinel\SubjectPermissionState;

final class CachedSubjectPermissionRevokerTest extends CacheTestCase
{
    public function test_revoker_does_not_remove_a_persisted_assignment_when_projection_contains_an_inherited_permission(): void
    {
        $subject    = $this->cachedSubject();
        $permission = $this->cachedPermission(10, 'posts.edit', 'Edit Posts');

        $sourcePermissions = $this->createMock(SubjectPermissionRepository::class);
        $sourcePermissions->expects(self::never())->method('remove');

        $projections = $this->projectionCache();
        $projections->save($subject, $this->projection([], [
            new ProjectionSubjectPermission('posts.edit', SubjectPermissionState::Inherited),
        ]));

        $cachedPermissions = new CachedSubjectPermissionRepository($sourcePermissions, $projections);
        $revoker           = new Revoker(
            $this->createStub(RoleRepository::class),
            $this->permissionRepository($permission),
            $this->createStub(SubjectRoleRepository::class),
            $cachedPermissions,
            $this->createStub(RolePermissionRepository::class),
        );

        $revoker->revoke($subject, $permission);

        self::assertSame(
            SubjectPermissionState::Inherited,
            $projections->load($subject)?->permissions()->find('posts.edit')?->state(),
        );
    }

    private function permissionRepository(Permission $permission): PermissionRepository
    {
        $repository = $this->createStub(PermissionRepository::class);
        $repository->method('lookup')
                   ->willReturn(new Permissions([$permission]));

        return $repository;
    }
}
