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
use Vaened\Sentinel\Cache\CachedSubjectPermissionRepository;
use Vaened\Sentinel\Projection\ProjectionSubjectPermission;
use Vaened\Sentinel\Repositories\SubjectPermissionRepository;
use Vaened\Sentinel\SubjectPermissions;
use Vaened\Sentinel\SubjectPermissionState;

final class CachedSubjectPermissionRepositoryTest extends CacheTestCase
{
    public function test_lookup_and_all_of_exclude_inherited_permissions(): void
    {
        $subject = $this->cachedSubject();

        $projections = $this->projectionCache();
        $projections->save($subject, $this->projection([], [
            new ProjectionSubjectPermission('posts.edit', SubjectPermissionState::Inherited),
            new ProjectionSubjectPermission('posts.delete', SubjectPermissionState::Direct),
        ]));

        $cached = new CachedSubjectPermissionRepository(
            $this->createStub(SubjectPermissionRepository::class),
            $projections,
        );

        self::assertSame(
            ['posts.delete'],
            $cached->lookup($subject, 'posts.edit', 'posts.delete')->codes(),
        );
        self::assertSame(['posts.delete'], $cached->allOf($subject)->codes());
    }

    public function test_lookup_normalizes_combined_projection_states_to_the_owned_repository_contract(): void
    {
        $subject = $this->cachedSubject();

        $projections = $this->projectionCache();
        $projections->save($subject, $this->projection([], [
            new ProjectionSubjectPermission('posts.edit', SubjectPermissionState::DirectInherited),
            new ProjectionSubjectPermission('posts.delete', SubjectPermissionState::DeniedInherited),
            new ProjectionSubjectPermission('posts.publish', SubjectPermissionState::Inherited),
        ]));

        $cached = new CachedSubjectPermissionRepository(
            $this->createStub(SubjectPermissionRepository::class),
            $projections,
        );

        $permissions = $cached->lookup($subject, 'posts.edit', 'posts.delete', 'posts.publish');

        self::assertSame(['posts.edit', 'posts.delete'], $permissions->codes());
        self::assertSame(SubjectPermissionState::Direct, $permissions->find('posts.edit')?->state());
        self::assertSame(SubjectPermissionState::Denied, $permissions->find('posts.delete')?->state());
    }

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

    public function test_create_forgets_the_cached_projection_and_rebuilds_from_the_source(): void
    {
        $subject     = $this->cachedSubject();
        $readUsers   = $this->cachedSubjectPermission(10, 'users.read');
        $createUsers = $this->cachedSubjectPermission(11, 'users.create');

        $repository = $this->createMock(SubjectPermissionRepository::class);
        $repository->expects(self::once())
                   ->method('allOf')
                   ->with($subject)
                   ->willReturn(new SubjectPermissions([$readUsers, $createUsers]));
        $repository->expects(self::once())
                   ->method('create')
                   ->with($subject, $createUsers);

        $projections = $this->projectionCache(permissions: $repository);
        $projections->save($subject, $this->projection([], [$readUsers]));

        $cached = new CachedSubjectPermissionRepository(
            $repository,
            $projections,
        );

        $cached->lookup($subject, 'users.read');
        $cached->create($subject, $createUsers);

        self::assertNull($projections->load($subject));

        $permissions = $cached->lookup($subject, 'users.create');

        self::assertSame(['users.create'], $permissions->codes());
        self::assertFalse($permissions->find('users.create')?->state()->isDenied());
    }

    public function test_update_forgets_the_cached_projection_and_rebuilds_from_the_source(): void
    {
        $subject          = $this->cachedSubject();
        $permission       = $this->cachedSubjectPermission(10, 'users.read');
        $deniedPermission = $this->cachedSubjectPermission(10, 'users.read', true);

        $repository = $this->createMock(SubjectPermissionRepository::class);
        $repository->expects(self::once())
                   ->method('allOf')
                   ->with($subject)
                   ->willReturn(new SubjectPermissions([$deniedPermission]));
        $repository->expects(self::once())
                   ->method('update')
                   ->with($subject, $deniedPermission);

        $projections = $this->projectionCache(permissions: $repository);
        $projections->save($subject, $this->projection([], [$permission]));

        $cached = new CachedSubjectPermissionRepository(
            $repository,
            $projections,
        );

        $cached->update($subject, $deniedPermission);

        self::assertNull($projections->load($subject));
        self::assertTrue($cached->lookup($subject, 'users.read')->find('users.read')?->state()->isDenied());
    }

    public function test_update_does_not_modify_the_existing_projection(): void
    {
        $subject          = $this->cachedSubject();
        $deniedPermission = $this->cachedSubjectPermission(10, 'users.read', true);

        $repository = $this->createMock(SubjectPermissionRepository::class);
        $repository->expects(self::once())
                   ->method('update')
                   ->with($subject, $deniedPermission);

        $projections = $this->projectionCache();
        $projections->save($subject, $this->projection([], [
            new ProjectionSubjectPermission('users.read', SubjectPermissionState::Inherited),
        ]));

        $cached = new CachedSubjectPermissionRepository($repository, $projections);

        $cached->update($subject, $deniedPermission);

        self::assertNull($projections->load($subject));
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

    public function test_purge_delegates_and_forgets_the_subject_projection(): void
    {
        $subject    = $this->cachedSubject();
        $repository = $this->createMock(SubjectPermissionRepository::class);
        $repository->expects(self::once())
                   ->method('purge')
                   ->with($subject);

        $projections = $this->projectionCache();
        $projections->save($subject, $this->projection(permissions: [
            $this->cachedSubjectPermission(10, 'users.read'),
        ]));

        $cached = new CachedSubjectPermissionRepository($repository, $projections);

        $cached->purge($subject);

        self::assertNull($projections->load($subject));
    }

    public function test_create_failure_does_not_forget_the_subject_projection(): void
    {
        $subject    = $this->cachedSubject();
        $permission = $this->cachedSubjectPermission(10, 'users.read');
        $repository = $this->createMock(SubjectPermissionRepository::class);
        $repository->expects(self::once())
                   ->method('create')
                   ->willThrowException(new RuntimeException('create failed'));

        $projections = $this->projectionCache();
        $projection  = $this->projection(permissions: [$permission]);
        $projections->save($subject, $projection);
        $cached = new CachedSubjectPermissionRepository($repository, $projections);

        try {
            $cached->create($subject, $permission);
            self::fail('Expected create to fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('create failed', $exception->getMessage());
        }

        self::assertSame($projection->toArray(), $projections->load($subject)?->toArray());
    }

    public function test_update_failure_does_not_forget_the_subject_projection(): void
    {
        $subject    = $this->cachedSubject();
        $permission = $this->cachedSubjectPermission(10, 'users.read');
        $repository = $this->createMock(SubjectPermissionRepository::class);
        $repository->expects(self::once())
                   ->method('update')
                   ->willThrowException(new RuntimeException('update failed'));

        $projections = $this->projectionCache();
        $projection  = $this->projection(permissions: [$permission]);
        $projections->save($subject, $projection);
        $cached = new CachedSubjectPermissionRepository($repository, $projections);

        try {
            $cached->update($subject, $permission);
            self::fail('Expected update to fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('update failed', $exception->getMessage());
        }

        self::assertSame($projection->toArray(), $projections->load($subject)?->toArray());
    }
}
