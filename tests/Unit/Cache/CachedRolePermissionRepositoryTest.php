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

use RuntimeException;
use Vaened\Sentinel\Authorizations;
use Vaened\Sentinel\Cache\CachedRolePermissionRepository;
use Vaened\Sentinel\Permissions;
use Vaened\Sentinel\Repositories\RolePermissionRepository;

final class CachedRolePermissionRepositoryTest extends CacheTestCase
{
    public function test_lookup_all_of_grants_and_exists_delegate_without_touching_the_cache_version(): void
    {
        $role       = $this->cachedRole(10, 'cashier', 'Cashier');
        $manager    = $this->cachedRole(11, 'manager', 'Manager');
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
                   ->method('grants')
                   ->with($role, $manager)
                   ->willReturn(new Permissions([$permission]));
        $repository->expects(self::once())
                   ->method('exists')
                   ->with(20)
                   ->willReturn(true);

        $cache          = $this->cacheStore();
        $cached         = new CachedRolePermissionRepository($repository, $cache);
        $initialVersion = $cache->currentVersion();

        self::assertSame(['documents.create'], $cached->lookup($role, 'documents.create')->codes());
        self::assertSame(['documents.create'], $cached->allOf($role)->codes());
        self::assertSame(['documents.create'], $cached->grants($role, $manager)->codes());
        self::assertTrue($cached->exists(20));
        self::assertSame($initialVersion, $cache->currentVersion());
    }

    public function test_create_invalidates_the_cache_after_delegating(): void
    {
        $role       = $this->cachedRole(10, 'cashier', 'Cashier');
        $permission = $this->cachedPermission(20, 'documents.create', 'Create Documents');

        $repository = $this->createMock(RolePermissionRepository::class);
        $repository->expects(self::once())
                   ->method('create')
                   ->with($role, $permission);

        $cache          = $this->cacheStore();
        $cached         = new CachedRolePermissionRepository($repository, $cache);
        $initialVersion = $cache->currentVersion();

        $cached->create($role, $permission);

        self::assertSame($initialVersion + 1, $cache->currentVersion());
    }

    public function test_remove_invalidates_the_cache_after_delegating(): void
    {
        $role       = $this->cachedRole(10, 'cashier', 'Cashier');
        $permission = $this->cachedPermission(20, 'documents.create', 'Create Documents');

        $repository = $this->createMock(RolePermissionRepository::class);
        $repository->expects(self::once())
                   ->method('remove')
                   ->with($role, $permission);

        $cache          = $this->cacheStore();
        $cached         = new CachedRolePermissionRepository($repository, $cache);
        $initialVersion = $cache->currentVersion();

        $cached->remove($role, $permission);

        self::assertSame($initialVersion + 1, $cache->currentVersion());
    }

    public function test_create_failure_does_not_invalidate_the_cache(): void
    {
        $role       = $this->cachedRole(10, 'cashier', 'Cashier');
        $permission = $this->cachedPermission(20, 'documents.create', 'Create Documents');

        $repository = $this->createMock(RolePermissionRepository::class);
        $repository->expects(self::once())
                   ->method('create')
                   ->willThrowException(new RuntimeException('create failed'));

        $cache          = $this->cacheStore();
        $cached         = new CachedRolePermissionRepository($repository, $cache);
        $initialVersion = $cache->currentVersion();

        try {
            $cached->create($role, $permission);
            self::fail('Expected create to fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('create failed', $exception->getMessage());
        }

        self::assertSame($initialVersion, $cache->currentVersion());
    }

    public function test_remove_failure_does_not_invalidate_the_cache(): void
    {
        $role       = $this->cachedRole(10, 'cashier', 'Cashier');
        $permission = $this->cachedPermission(20, 'documents.create', 'Create Documents');

        $repository = $this->createMock(RolePermissionRepository::class);
        $repository->expects(self::once())
                   ->method('remove')
                   ->willThrowException(new RuntimeException('remove failed'));

        $cache          = $this->cacheStore();
        $cached         = new CachedRolePermissionRepository($repository, $cache);
        $initialVersion = $cache->currentVersion();

        try {
            $cached->remove($role, $permission);
            self::fail('Expected remove to fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('remove failed', $exception->getMessage());
        }

        self::assertSame($initialVersion, $cache->currentVersion());
    }
}
