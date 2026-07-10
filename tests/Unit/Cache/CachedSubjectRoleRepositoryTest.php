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

use Vaened\Sentinel\Authorizations;
use Vaened\Sentinel\Cache\CachedSubjectRoleRepository;
use Vaened\Sentinel\Projection\SubjectAuthorizationProjection;
use Vaened\Sentinel\Repositories\RolePermissionRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;
use Vaened\Sentinel\SubjectPermissionState;

final class CachedSubjectRoleRepositoryTest extends CacheTestCase
{
    public function test_lookup_and_all_of_are_resolved_from_the_cached_projection(): void
    {
        $subject    = $this->cachedSubject();
        $projection = new SubjectAuthorizationProjection(['cashier'], []);

        $repository      = $this->createStub(SubjectRoleRepository::class);
        $rolePermissions = $this->createStub(RolePermissionRepository::class);
        $projections     = $this->projectionCache();
        $projections->save($subject, $projection);

        $cached = new CachedSubjectRoleRepository(
            $repository,
            $rolePermissions,
            $projections,
        );

        self::assertSame(['cashier'], $cached->lookup($subject, 'cashier', 'admin')->codes());
        self::assertSame(['cashier'], $cached->allOf($subject)->codes());
    }

    public function test_grants_delegates_to_the_source_repository_and_skips_the_projection_cache(): void
    {
        $subject    = $this->cachedSubject();
        $permission = $this->cachedPermission(20, 'documents.create', 'Create Documents');

        $repository = $this->createMock(SubjectRoleRepository::class);
        $repository->expects(self::once())
                   ->method('grants')
                   ->with($subject, ['documents.create', 'documents.annul'])
                   ->willReturn(new Authorizations([$permission]));

        $rolePermissions = $this->createMock(RolePermissionRepository::class);
        $rolePermissions->expects(self::never())->method('allOf');

        $cached = new CachedSubjectRoleRepository(
            $repository,
            $rolePermissions,
            $this->projectionCache(),
        );

        self::assertSame(['documents.create'], $cached->grants($subject, ['documents.create', 'documents.annul'])->codes());
    }

    public function test_create_updates_the_cached_projection_using_the_role_permissions(): void
    {
        $subject         = $this->cachedSubject();
        $role            = $this->cachedRole(10, 'cashier', 'Cashier');
        $createDocuments = $this->cachedPermission(20, 'documents.create', 'Create Documents');
        $initial         = new SubjectAuthorizationProjection([], []);

        $repository = $this->createMock(SubjectRoleRepository::class);
        $repository->expects(self::once())
                   ->method('create')
                   ->with($subject, $role);

        $rolePermissions = $this->createMock(RolePermissionRepository::class);
        $rolePermissions->expects(self::once())
                        ->method('allOf')
                        ->with($role)
                        ->willReturn(new Authorizations([$createDocuments]));

        $projections = $this->projectionCache();
        $projections->save($subject, $initial);

        $cached = new CachedSubjectRoleRepository(
            $repository,
            $rolePermissions,
            $projections,
        );

        $cached->create($subject, $role);

        self::assertSame(['cashier'], $projections->load($subject)?->roles());
        self::assertSame(['documents.create' => SubjectPermissionState::Inherited->value], $projections->load($subject)?->permissions());
    }

    public function test_remove_forgets_the_subject_projection_and_reloads_it_on_the_next_lookup(): void
    {
        $subject    = $this->cachedSubject();
        $role       = $this->cachedRole(10, 'cashier', 'Cashier');
        $repository = $this->createMock(SubjectRoleRepository::class);
        $repository->expects(self::once())
                   ->method('allOf')
                   ->with($subject)
                   ->willReturn(new Authorizations([]));
        $repository->expects(self::once())
                   ->method('grants')
                   ->with($subject)
                   ->willReturn(new Authorizations([]));
        $repository->expects(self::once())
                   ->method('remove')
                   ->with($subject, $role);

        $rolePermissions = $this->createStub(RolePermissionRepository::class);
        $projections     = $this->projectionCache(
            roles: $repository,
        );
        $projections->save($subject, new SubjectAuthorizationProjection(['cashier'], []));

        $cached = new CachedSubjectRoleRepository(
            $repository,
            $rolePermissions,
            $projections,
        );

        self::assertSame(['cashier'], $cached->lookup($subject, 'cashier')->codes());
        $cached->remove($subject, $role);

        $cached->lookup($subject, 'admin');
    }
}
