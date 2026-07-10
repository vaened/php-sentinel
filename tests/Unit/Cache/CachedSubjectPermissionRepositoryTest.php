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
use Vaened\Sentinel\Projection\SubjectAuthorizationProjection;
use Vaened\Sentinel\Repositories\SubjectPermissionRepository;
use Vaened\Sentinel\SubjectPermissionState;
use Vaened\Sentinel\SubjectPermissions;

final class CachedSubjectPermissionRepositoryTest extends CacheTestCase
{
    public function test_lookup_reads_subject_permissions_from_cache_after_the_first_load(): void
    {
        $subject     = $this->cachedSubject();
        $readUsers   = $this->cachedSubjectPermission(10, 'users.read');
        $deleteUsers = $this->cachedSubjectPermission(11, 'users.delete', true);

        $repository = $this->createMock(SubjectPermissionRepository::class);
        $repository->expects(self::once())
                   ->method('allOf')
                   ->with($subject)
                   ->willReturn(new SubjectPermissions([$readUsers, $deleteUsers]));

        $cached = new CachedSubjectPermissionRepository(
            $repository,
            $this->projectionCache(
                permissions: $repository,
            ),
        );

        $first  = $cached->lookup($subject, 'users.read', 'users.delete');
        $second = $cached->lookup($subject, 'users.read', 'users.delete');

        self::assertSame(['users.read', 'users.delete'], $first->codes());
        self::assertFalse($first->find('users.read')?->state()->isDenied());
        self::assertTrue($first->find('users.delete')?->state()->isDenied());
        self::assertSame($first->codes(), $second->codes());
    }

    public function test_create_updates_the_cached_subject_permissions_without_reloading_the_source_repository(): void
    {
        $subject     = $this->cachedSubject();
        $readUsers   = $this->cachedSubjectPermission(10, 'users.read');
        $createUsers = $this->cachedSubjectPermission(11, 'users.create');

        $repository = $this->createMock(SubjectPermissionRepository::class);
        $repository->expects(self::never())
                   ->method('allOf');
        $repository->expects(self::once())
                   ->method('create')
                   ->with($subject, $createUsers);

        $projections = $this->projectionCache();
        $projections->save($subject, new SubjectAuthorizationProjection([], ['users.read' => SubjectPermissionState::Direct->value]));

        $cached = new CachedSubjectPermissionRepository(
            $repository,
            $projections,
        );

        $cached->lookup($subject, 'users.read');
        $cached->create($subject, $createUsers);

        $permissions = $cached->lookup($subject, 'users.create');
        $projection  = $projections->load($subject);

        self::assertSame(['users.create'], $permissions->codes());
        self::assertFalse($permissions->find('users.create')?->state()->isDenied());
        self::assertSame([
            'users.read'   => 1,
            'users.create' => 1,
        ], $projection?->permissions());
    }

    public function test_update_overwrites_the_cached_permission_state_without_reloading_the_source_repository(): void
    {
        $subject          = $this->cachedSubject();
        $permission       = $this->cachedSubjectPermission(10, 'users.read');
        $deniedPermission = $this->cachedSubjectPermission(10, 'users.read', true);

        $repository = $this->createMock(SubjectPermissionRepository::class);
        $repository->expects(self::never())
                   ->method('allOf');
        $repository->expects(self::once())
                   ->method('update')
                   ->with($subject, $deniedPermission);

        $projections = $this->projectionCache();
        $projections->save($subject, new SubjectAuthorizationProjection([], ['users.read' => SubjectPermissionState::Direct->value]));

        $cached = new CachedSubjectPermissionRepository(
            $repository,
            $projections,
        );

        $cached->lookup($subject, 'users.read');
        $cached->update($subject, $deniedPermission);

        self::assertTrue($cached->lookup($subject, 'users.read')->find('users.read')?->state()->isDenied());
    }

    public function test_remove_forgets_the_subject_projection_and_reloads_it_on_the_next_lookup(): void
    {
        $subject    = $this->cachedSubject();
        $permission = $this->cachedSubjectPermission(10, 'users.read');
        $repository = $this->createMock(SubjectPermissionRepository::class);
        $repository->expects(self::exactly(2))
                   ->method('allOf')
                   ->with($subject)
                   ->willReturnOnConsecutiveCalls(
                       new SubjectPermissions([$permission]),
                       new SubjectPermissions([]),
                   );
        $repository->expects(self::once())
                   ->method('remove')
                   ->with($subject, $permission);

        $projections = $this->projectionCache(
            permissions: $repository,
        );

        $cached = new CachedSubjectPermissionRepository(
            $repository,
            $projections,
        );

        $cached->lookup($subject, 'users.read');

        $cached->remove($subject, $permission);

        $cached->lookup($subject, 'users.update');
    }
}
