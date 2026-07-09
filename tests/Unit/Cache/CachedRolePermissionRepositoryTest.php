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
use Vaened\Sentinel\Cache\CachedRolePermissionRepository;
use Vaened\Sentinel\Repositories\RolePermissionRepository;

final class CachedRolePermissionRepositoryTest extends CacheTestCase
{
    public function test_lookup_all_of_and_exists_delegate_without_touching_the_cache_version(): void
    {
        $role       = $this->cachedRole(10, 'cashier', 'Cashier');
        $permission = $this->cachedPermission(20, 'documents.create', 'Create Documents');

        $repository = $this->createMock(RolePermissionRepository::class);
        $repository->expects(self::once())
                   ->method('lookup')
                   ->with($role, 'documents.create')
                   ->willReturn(new Authorizations([$permission]));
        $repository->expects(self::once())
                   ->method('allOf')
                   ->with($role)
                   ->willReturn(new Authorizations([$permission]));
        $repository->expects(self::once())
                   ->method('exists')
                   ->with(20)
                   ->willReturn(true);

        $cache  = $this->cacheStore();
        $cached = new CachedRolePermissionRepository($repository, $cache);

        self::assertSame(['documents.create'], $cached->lookup($role, 'documents.create')->codes());
        self::assertSame(['documents.create'], $cached->allOf($role)->codes());
        self::assertTrue($cached->exists(20));
        self::assertSame(1, $this->cacheVersion($cache));
    }

    public function test_create_invalidates_the_cache_after_delegating(): void
    {
        $role       = $this->cachedRole(10, 'cashier', 'Cashier');
        $permission = $this->cachedPermission(20, 'documents.create', 'Create Documents');

        $repository = $this->createMock(RolePermissionRepository::class);
        $repository->expects(self::once())
                   ->method('create')
                   ->with($role, $permission);

        $cache  = $this->cacheStore();
        $cached = new CachedRolePermissionRepository($repository, $cache);

        $cached->create($role, $permission);

        self::assertSame(2, $this->cacheVersion($cache));
    }

    public function test_remove_invalidates_the_cache_after_delegating(): void
    {
        $role       = $this->cachedRole(10, 'cashier', 'Cashier');
        $permission = $this->cachedPermission(20, 'documents.create', 'Create Documents');

        $repository = $this->createMock(RolePermissionRepository::class);
        $repository->expects(self::once())
                   ->method('remove')
                   ->with($role, $permission);

        $cache  = $this->cacheStore();
        $cached = new CachedRolePermissionRepository($repository, $cache);

        $cached->remove($role, $permission);

        self::assertSame(2, $this->cacheVersion($cache));
    }
}
