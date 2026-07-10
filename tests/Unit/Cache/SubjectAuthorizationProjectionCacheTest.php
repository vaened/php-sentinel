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
use Vaened\Sentinel\Cache\SubjectAuthorizationProjectionCache;
use Vaened\Sentinel\Projection\SubjectAuthorizationProjection;
use Vaened\Sentinel\Repositories\SubjectPermissionRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;
use Vaened\Sentinel\SubjectPermissionState;
use Vaened\Sentinel\SubjectPermissions;

final class SubjectAuthorizationProjectionCacheTest extends CacheTestCase
{
    public function test_load_or_build_hits_the_source_repositories_only_once_per_subject(): void
    {
        $subject         = $this->cachedSubject();
        $role            = $this->cachedRole(10, 'cashier', 'Cashier');
        $createDocuments = $this->cachedPermission(20, 'documents.create', 'Create Documents');
        $annulDocuments  = $this->cachedSubjectPermission(21, 'documents.annul', true);

        $roles = $this->createMock(SubjectRoleRepository::class);
        $roles->expects(self::once())
              ->method('allOf')
              ->with($subject)
              ->willReturn(new Authorizations([$role]));
        $roles->expects(self::once())
              ->method('grants')
              ->with($subject)
              ->willReturn(new Authorizations([$createDocuments]));

        $permissions = $this->createMock(SubjectPermissionRepository::class);
        $permissions->expects(self::once())
                    ->method('allOf')
                    ->with($subject)
                    ->willReturn(new SubjectPermissions([$annulDocuments]));

        $cache = new SubjectAuthorizationProjectionCache(
            $this->cacheStore(),
            $roles,
            $permissions,
        );

        $first  = $cache->loadOrBuild($subject);
        $second = $cache->loadOrBuild($subject);

        self::assertSame(['cashier'], $first->roles());
        self::assertSame([
            'documents.annul'  => 0,
            'documents.create' => 2,
        ], $first->permissions());
        self::assertSame($first->toArray(), $second->toArray());
    }

    public function test_bump_version_invalidates_cached_projections_and_forces_them_to_reload(): void
    {
        $subject    = $this->cachedSubject();
        $role       = $this->cachedRole(10, 'cashier', 'Cashier');
        $permission = $this->cachedPermission(20, 'documents.create', 'Create Documents');

        $roles = $this->createMock(SubjectRoleRepository::class);
        $roles->expects(self::exactly(2))
              ->method('allOf')
              ->with($subject)
              ->willReturn(new Authorizations([$role]));
        $roles->expects(self::exactly(2))
              ->method('grants')
              ->with($subject)
              ->willReturn(new Authorizations([$permission]));

        $permissions = $this->createMock(SubjectPermissionRepository::class);
        $permissions->expects(self::exactly(2))
                    ->method('allOf')
                    ->with($subject)
                    ->willReturn(new SubjectPermissions([]));

        $cache = new SubjectAuthorizationProjectionCache(
            $this->cacheStore(),
            $roles,
            $permissions,
        );

        self::assertSame(['cashier'], $cache->loadOrBuild($subject)->roles());

        $cache->bumpVersion();

        self::assertSame(['cashier'], $cache->loadOrBuild($subject)->roles());
    }

    public function test_with_role_added_keeps_direct_subject_denials_when_role_also_grants_the_same_code(): void
    {
        $admin = $this->cachedRole(10, 'admin', 'Administrator');

        $initial = new SubjectAuthorizationProjection(
            roles:       [],
            permissions: ['documents.annul' => SubjectPermissionState::Denied->value],
        );

        $cache = new SubjectAuthorizationProjectionCache(
            $this->cacheStore(),
            $this->createStub(SubjectRoleRepository::class),
            $this->createStub(SubjectPermissionRepository::class),
        );

        $merged = $cache->withRoleAdded(
            $initial,
            $admin,
            ['documents.annul', 'users.read'],
        );

        self::assertSame(['admin'], $merged->roles());
        self::assertSame(
            SubjectPermissionState::Denied->value,
            $merged->permissions()['documents.annul'],
            'A direct subject denial must NOT be reverted by role inheritance.',
        );
        self::assertSame(
            SubjectPermissionState::Inherited->value,
            $merged->permissions()['users.read'],
            'A role-granted code that the subject had no direct entry for must be added.',
        );
    }

    public function test_with_role_added_is_a_noop_when_the_role_is_already_attached(): void
    {
        $admin = $this->cachedRole(10, 'admin', 'Administrator');

        $initial = new SubjectAuthorizationProjection(
            roles:       ['admin'],
            permissions: ['users.read' => SubjectPermissionState::Direct->value],
        );

        $cache = new SubjectAuthorizationProjectionCache(
            $this->cacheStore(),
            $this->createStub(SubjectRoleRepository::class),
            $this->createStub(SubjectPermissionRepository::class),
        );

        $result = $cache->withRoleAdded($initial, $admin, ['users.read']);

        self::assertSame(['admin'], $result->roles());
        self::assertSame(['users.read' => 1], $result->permissions());
    }
}
