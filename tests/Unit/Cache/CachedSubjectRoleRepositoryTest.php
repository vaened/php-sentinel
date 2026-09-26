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
use Vaened\Sentinel\Cache\CachedSubjectRoleRepository;
use Vaened\Sentinel\Projection\ProjectionSubjectPermission;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;
use Vaened\Sentinel\SubjectPermissionState;

final class CachedSubjectRoleRepositoryTest extends CacheTestCase
{
    public function test_lookup_and_all_of_are_resolved_from_the_cached_projection(): void
    {
        $subject    = $this->cachedSubject();
        $cashier    = $this->cachedRole(10, 'cashier', 'Cashier');
        $projection = $this->projection([$cashier]);

        $repository  = $this->createStub(SubjectRoleRepository::class);
        $projections = $this->projectionCache();
        $projections->save($subject, $projection);

        $cached = new CachedSubjectRoleRepository(
            $repository,
            $projections,
        );

        self::assertSame(['cashier'], $cached->lookup($subject, 'cashier', 'admin')->codes());
        self::assertSame(['cashier'], $cached->allOf($subject)->codes());
    }

    public function test_grants_are_resolved_from_the_cached_projection_including_combined_states(): void
    {
        $subject = $this->cachedSubject();

        $repository = $this->createMock(SubjectRoleRepository::class);
        $repository->expects(self::never())->method('grants');

        $projections = $this->projectionCache();
        $projections->save($subject, $this->projection(permissions: [
            new ProjectionSubjectPermission('documents.create', SubjectPermissionState::Inherited),
            new ProjectionSubjectPermission('documents.update', SubjectPermissionState::DirectInherited),
            new ProjectionSubjectPermission('documents.annul', SubjectPermissionState::DeniedInherited),
            new ProjectionSubjectPermission('documents.delete', SubjectPermissionState::Direct),
        ]));

        $cached = new CachedSubjectRoleRepository(
            $repository,
            $projections,
        );

        self::assertSame(['documents.update', 'documents.annul'],
            $cached->grants($subject, ['documents.update', 'documents.annul'])->codes());
        self::assertSame(['documents.create', 'documents.update', 'documents.annul'], $cached->grants($subject)->codes());
        self::assertSame([], $cached->grants($subject, [])->codes());
    }

    public function test_create_forgets_the_cached_projection_and_rebuilds_from_the_source(): void
    {
        $subject = $this->cachedSubject();
        $role    = $this->cachedRole(10, 'cashier', 'Cashier');

        $repository = $this->createMock(SubjectRoleRepository::class);
        $repository->expects(self::once())
                   ->method('create')
                   ->with($subject, $role);
        $repository->expects(self::once())
                   ->method('allOf')
                   ->with($subject)
                   ->willReturn(new Authorizations([$role]));
        $repository->expects(self::once())
                   ->method('grants')
                   ->with($subject)
                   ->willReturn(new Authorizations([]));

        $projections = $this->projectionCache(roles: $repository);
        $projections->save($subject, $this->projection());

        $cached = new CachedSubjectRoleRepository(
            $repository,
            $projections,
        );

        $cached->create($subject, $role);

        self::assertNull($projections->load($subject));
        self::assertSame(['cashier'], $cached->lookup($subject, 'cashier')->codes());
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

        $projections = $this->projectionCache(
            roles: $repository,
        );
        $projections->save($subject, $this->projection([$role]));

        $cached = new CachedSubjectRoleRepository(
            $repository,
            $projections,
        );

        self::assertSame(['cashier'], $cached->lookup($subject, 'cashier')->codes());
        $cached->remove($subject, $role);

        $cached->lookup($subject, 'admin');
    }

    public function test_purge_delegates_and_forgets_the_subject_projection(): void
    {
        $subject    = $this->cachedSubject();
        $repository = $this->createMock(SubjectRoleRepository::class);
        $repository->expects(self::once())
                   ->method('purge')
                   ->with($subject);

        $projections = $this->projectionCache();
        $projections->save($subject, $this->projection([
            $this->cachedRole(10, 'cashier', 'Cashier'),
        ]));

        $cached = new CachedSubjectRoleRepository(
            $repository,
            $projections,
        );

        $cached->purge($subject);

        self::assertNull($projections->load($subject));
    }

    public function test_create_failure_does_not_forget_the_subject_projection(): void
    {
        $subject    = $this->cachedSubject();
        $role       = $this->cachedRole(10, 'cashier', 'Cashier');
        $repository = $this->createMock(SubjectRoleRepository::class);
        $repository->expects(self::once())
                   ->method('create')
                   ->willThrowException(new RuntimeException('create failed'));

        $projections = $this->projectionCache();
        $projection  = $this->projection([$role]);
        $projections->save($subject, $projection);
        $cached = new CachedSubjectRoleRepository($repository, $projections);

        try {
            $cached->create($subject, $role);
            self::fail('Expected create to fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('create failed', $exception->getMessage());
        }

        self::assertSame($projection->toArray(), $projections->load($subject)?->toArray());
    }
}
