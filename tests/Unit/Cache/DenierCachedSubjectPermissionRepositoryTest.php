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
use Vaened\Sentinel\Operators\Denier;
use Vaened\Sentinel\Operators\SubjectPermissionSnapshot;
use Vaened\Sentinel\Permissions;
use Vaened\Sentinel\Repositories\RoleRepository;
use Vaened\Sentinel\Repositories\SubjectPermissionRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;
use Vaened\Sentinel\Roles;
use Vaened\Sentinel\SubjectPermissions;

final class DenierCachedSubjectPermissionRepositoryTest extends CacheTestCase
{
    public function test_denier_tries_to_update_when_cached_lookup_only_sees_an_inherited_permission(): void
    {
        $subject    = $this->cachedSubject();
        $permission = $this->cachedPermission(10, 'posts.edit', 'Edit Posts');
        $role       = $this->cachedRole(20, 'admin', 'Administrator');

        $subjectRoles = $this->createMock(SubjectRoleRepository::class);
        $subjectRoles->expects(self::once())
                     ->method('allOf')
                     ->with($subject)
                     ->willReturn(new Roles([$role]));
        $subjectRoles->expects(self::once())
                     ->method('grants')
                     ->with($subject)
                     ->willReturn(new Permissions([$permission]));

        $sourcePermissions = $this->createMock(SubjectPermissionRepository::class);
        $sourcePermissions->expects(self::once())
                          ->method('allOf')
                          ->with($subject)
                          ->willReturn(new SubjectPermissions([]));
        $sourcePermissions->expects(self::once())
                          ->method('create')
                          ->with(
                              $subject,
                              self::callback(
                                  static fn(SubjectPermissionSnapshot $permission): bool => $permission->code() === 'posts.edit'
                                      && $permission->isDenied(),
                              ),
                          );
        $sourcePermissions->expects(self::never())
                          ->method('update');

        $cachedPermissions = new CachedSubjectPermissionRepository(
            $sourcePermissions,
            $this->projectionCache(
                roles      : $subjectRoles,
                permissions: $sourcePermissions,
            ),
        );

        $cachedPermissions->lookup($subject, 'posts.edit');

        $denier = new Denier(
            $this->createStub(RoleRepository::class),
            $this->permissionRepository($permission),
            $cachedPermissions,
        );

        $denier->deny($subject, $permission);
    }

    private function permissionRepository($permission)
    {
        $repository = $this->createStub(\Vaened\Sentinel\Repositories\PermissionRepository::class);
        $repository->method('lookup')
                   ->with('posts.edit')
                   ->willReturn(new Permissions([$permission]));

        return $repository;
    }
}
