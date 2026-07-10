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

use Vaened\Sentinel\Cache\CachedPermissionRepository;
use Vaened\Sentinel\Permissions;
use Vaened\Sentinel\Repositories\PermissionRepository;

final class CachedPermissionRepositoryTest extends CacheTestCase
{
    public function test_lookup_exists_create_and_update_delegate_without_touching_the_cache_version(): void
    {
        $permission = $this->cachedPermission(20, 'documents.create', 'Create Documents');

        $repository = $this->createMock(PermissionRepository::class);
        $repository->expects(self::once())
                   ->method('lookup')
                   ->with('documents.create')
                   ->willReturn(new Permissions([$permission]));
        $repository->expects(self::once())
                   ->method('exists')
                   ->with(20)
                   ->willReturn(true);
        $repository->expects(self::once())
                   ->method('create')
                   ->with('documents.create', 'Create Documents', null)
                   ->willReturn($permission);
        $repository->expects(self::once())
                   ->method('update')
                   ->with(20, 'Create Documents', 'Allows document creation');

        $cache  = $this->cacheStore();
        $cached = new CachedPermissionRepository($repository, $cache);

        self::assertSame(['documents.create'], $cached->lookup('documents.create')->codes());
        self::assertTrue($cached->exists(20));
        self::assertSame($permission, $cached->create('documents.create', 'Create Documents'));
        $cached->update(20, 'Create Documents', 'Allows document creation');

        self::assertSame(1, $cache->currentVersion());
    }

    public function test_remove_invalidates_the_cache_after_delegating_to_the_source_repository(): void
    {
        $repository = $this->createMock(PermissionRepository::class);
        $repository->expects(self::once())
                   ->method('remove')
                   ->with(20);

        $cache  = $this->cacheStore();
        $cached = new CachedPermissionRepository($repository, $cache);

        $cached->remove(20);

        self::assertSame(2, $cache->currentVersion());
    }
}
