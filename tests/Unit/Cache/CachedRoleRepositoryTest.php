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
use Vaened\Sentinel\Cache\CachedRoleRepository;
use Vaened\Sentinel\Repositories\RoleRepository;

final class CachedRoleRepositoryTest extends CacheTestCase
{
    public function test_lookup_exists_create_and_update_delegate_without_touching_the_cache_version(): void
    {
        $role = $this->cachedRole(10, 'cashier', 'Cashier');

        $repository = $this->createMock(RoleRepository::class);
        $repository->expects(self::once())
                   ->method('lookup')
                   ->with('cashier')
                   ->willReturn(new Authorizations([$role]));
        $repository->expects(self::once())
                   ->method('exists')
                   ->with(10)
                   ->willReturn(true);
        $repository->expects(self::once())
                   ->method('create')
                   ->with('cashier', 'Cashier', null)
                   ->willReturn($role);
        $repository->expects(self::once())
                   ->method('update')
                   ->with(10, 'Cashier', 'Front desk role');

        $cache  = $this->cacheStore();
        $cached = new CachedRoleRepository($repository, $cache);

        self::assertSame(['cashier'], $cached->lookup('cashier')->codes());
        self::assertTrue($cached->exists(10));
        self::assertSame($role, $cached->create('cashier', 'Cashier'));
        $cached->update(10, 'Cashier', 'Front desk role');

        self::assertSame(1, $cache->currentVersion());
    }

    public function test_remove_invalidates_the_cache_after_delegating_to_the_source_repository(): void
    {
        $repository = $this->createMock(RoleRepository::class);
        $repository->expects(self::once())
                   ->method('remove')
                   ->with(10);

        $cache  = $this->cacheStore();
        $cached = new CachedRoleRepository($repository, $cache);

        $cached->remove(10);

        self::assertSame(2, $cache->currentVersion());
    }
}
