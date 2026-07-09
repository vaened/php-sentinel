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
use Vaened\Sentinel\Repositories\SubjectPermissionRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;
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
            'documents.annul'  => false,
            'documents.create' => true,
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
}
