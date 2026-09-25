<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Tests\Integration;

use Vaened\Sentinel\Authorization\Authorizer;
use Vaened\Sentinel\Authorization\PermissionEntryProvider;
use Vaened\Sentinel\Authorization\RoleEntryProvider;
use Vaened\Sentinel\Authorizations;
use Vaened\Sentinel\Cache\CachedRepositories;
use Vaened\Sentinel\Cache\CacheSettings;
use Vaened\Sentinel\Cache\SentinelCacheFactory;
use Vaened\Sentinel\Operators\SubjectPermissionSnapshot;
use Vaened\Sentinel\Repositories\PermissionRepository;
use Vaened\Sentinel\Repositories\RolePermissionRepository;
use Vaened\Sentinel\Repositories\RoleRepository;
use Vaened\Sentinel\Repositories\SubjectPermissionRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;
use Vaened\Sentinel\SubjectPermissions;
use Vaened\Sentinel\Tests\Runtime\InMemoryCache;
use Vaened\Sentinel\Tests\Runtime\TestPermission;
use Vaened\Sentinel\Tests\Runtime\TestRole;
use Vaened\Sentinel\Tests\Runtime\TestSubject;
use Vaened\Sentinel\Tests\Runtime\TestSubjectPermission;
use Vaened\Sentinel\Tests\TestCase;

final class CachedAuthorizerCacheFlowTest extends TestCase
{
    public function test_direct_permission_reads_persistence_once_then_uses_the_cache(): void
    {
        $subject    = new TestSubject(1);
        $permission = new TestPermission(10, 'posts.edit', 'Edit Posts');
        $calls      = $this->persistenceCalls();
        $authorizer = $this->cachedAuthorizer(
            $subject,
            new Authorizations([]),
            new Authorizations([]),
            new SubjectPermissions([TestSubjectPermission::from($permission)]),
            $calls,
        );

        self::assertTrue($authorizer->can($subject, ['posts.edit']));
        self::assertSame(['roles' => 1, 'grants' => 1, 'permissions' => 1], $calls);

        self::assertTrue($authorizer->can($subject, ['posts.edit']));
        self::assertSame(['roles' => 1, 'grants' => 1, 'permissions' => 1], $calls);
    }

    public function test_inherited_permission_reads_persistence_once_then_uses_the_cache(): void
    {
        $subject    = new TestSubject(1);
        $role       = new TestRole(20, 'editor', 'Editor');
        $permission = new TestPermission(10, 'posts.edit', 'Edit Posts');
        $calls      = $this->persistenceCalls();
        $authorizer = $this->cachedAuthorizer(
            $subject,
            new Authorizations([$role]),
            new Authorizations([$permission]),
            new SubjectPermissions([]),
            $calls,
        );

        self::assertTrue($authorizer->can($subject, ['posts.edit']));
        self::assertSame(['roles' => 1, 'grants' => 1, 'permissions' => 1], $calls);

        self::assertTrue($authorizer->can($subject, ['posts.edit']));
        self::assertSame(['roles' => 1, 'grants' => 1, 'permissions' => 1], $calls);
    }

    public function test_role_reads_persistence_once_then_uses_the_cache(): void
    {
        $subject    = new TestSubject(1);
        $role       = new TestRole(20, 'editor', 'Editor');
        $permission = new TestPermission(10, 'posts.edit', 'Edit Posts');
        $calls      = $this->persistenceCalls();
        $authorizer = $this->cachedAuthorizer(
            $subject,
            new Authorizations([$role]),
            new Authorizations([$permission]),
            new SubjectPermissions([]),
            $calls,
        );

        self::assertTrue($authorizer->is($subject, ['editor']));
        self::assertSame(['roles' => 1, 'grants' => 1, 'permissions' => 1], $calls);

        self::assertTrue($authorizer->is($subject, ['editor']));
        self::assertSame(['roles' => 1, 'grants' => 1, 'permissions' => 1], $calls);
    }

    public function test_missing_permission_reads_persistence_once_then_uses_the_cache(): void
    {
        $subject    = new TestSubject(1);
        $calls      = $this->persistenceCalls();
        $authorizer = $this->cachedAuthorizer(
            $subject,
            new Authorizations([]),
            new Authorizations([]),
            new SubjectPermissions([]),
            $calls,
        );

        self::assertFalse($authorizer->can($subject, ['posts.edit']));
        self::assertSame(['roles' => 1, 'grants' => 1, 'permissions' => 1], $calls);

        self::assertFalse($authorizer->can($subject, ['posts.edit']));
        self::assertSame(['roles' => 1, 'grants' => 1, 'permissions' => 1], $calls);
    }

    public function test_assigning_a_role_updates_only_that_subjects_warm_projection(): void
    {
        $first       = new TestSubject(1);
        $second      = new TestSubject(2);
        $role        = new TestRole(20, 'editor', 'Editor');
        $permission  = new TestPermission(10, 'posts.edit', 'Edit Posts');
        $firstCalls  = $this->persistenceCalls();
        $secondCalls = $this->persistenceCalls();

        $subjectRoles = $this->createMock(SubjectRoleRepository::class);
        $subjectRoles->method('allOf')
                     ->willReturnCallback(function (TestSubject $subject) use (&$firstCalls, &$secondCalls, $first): Authorizations {
                         if ($subject === $first) {
                             $firstCalls['roles']++;
                         } else {
                             $secondCalls['roles']++;
                         }

                         return new Authorizations([]);
                     });
        $subjectRoles->method('grants')
                     ->willReturnCallback(function (TestSubject $subject) use (&$firstCalls, &$secondCalls, $first): Authorizations {
                         if ($subject === $first) {
                             $firstCalls['grants']++;
                         } else {
                             $secondCalls['grants']++;
                         }

                         return new Authorizations([]);
                     });
        $subjectRoles->expects(self::once())->method('create')->with($first, $role);

        $subjectPermissions = $this->createMock(SubjectPermissionRepository::class);
        $subjectPermissions->method('allOf')
                           ->willReturnCallback(function (TestSubject $subject) use (
                               &$firstCalls,
                               &$secondCalls,
                               $first
                           ): SubjectPermissions {
                               if ($subject === $first) {
                                   $firstCalls['permissions']++;
                               } else {
                                   $secondCalls['permissions']++;
                               }

                               return new SubjectPermissions([]);
                           });

        $rolePermissions = $this->createMock(RolePermissionRepository::class);
        $rolePermissions->expects(self::once())
                        ->method('allOf')
                        ->with($role)
                        ->willReturn(new Authorizations([$permission]));

        [$authorizer, $cached] = $this->cachedContext($subjectRoles, $subjectPermissions, $rolePermissions);

        self::assertFalse($authorizer->can($first, ['posts.edit']));
        self::assertFalse($authorizer->can($second, ['posts.edit']));
        self::assertSame(['roles' => 1, 'grants' => 1, 'permissions' => 1], $firstCalls);
        self::assertSame(['roles' => 1, 'grants' => 1, 'permissions' => 1], $secondCalls);

        $cached->subjectRoleRepository()->create($first, $role);

        self::assertTrue($authorizer->can($first, ['posts.edit']));
        self::assertFalse($authorizer->can($second, ['posts.edit']));
        self::assertSame(['roles' => 1, 'grants' => 1, 'permissions' => 1], $firstCalls);
        self::assertSame(['roles' => 1, 'grants' => 1, 'permissions' => 1], $secondCalls);
    }

    public function test_removing_a_role_rebuilds_only_that_subjects_projection_once(): void
    {
        $first       = new TestSubject(1);
        $second      = new TestSubject(2);
        $role        = new TestRole(20, 'editor', 'Editor');
        $permission  = new TestPermission(10, 'posts.edit', 'Edit Posts');
        $firstCalls  = $this->persistenceCalls();
        $secondCalls = $this->persistenceCalls();
        $hasRole     = true;

        $subjectRoles = $this->createMock(SubjectRoleRepository::class);
        $subjectRoles->method('allOf')
                     ->willReturnCallback(function (TestSubject $subject) use (
                         &$firstCalls,
                         &$secondCalls,
                         $first,
                         $second,
                         $role,
                         &$hasRole,
                     ): Authorizations {
                         if ($subject === $first) {
                             $firstCalls['roles']++;
                         } else {
                             $secondCalls['roles']++;
                         }

                         return $hasRole || $subject === $second
                             ? new Authorizations([$role])
                             : new Authorizations([]);
                     });
        $subjectRoles->method('grants')
                     ->willReturnCallback(function (TestSubject $subject) use (
                         &$firstCalls,
                         &$secondCalls,
                         $first,
                         $second,
                         $permission,
                         &$hasRole,
                     ): Authorizations {
                         if ($subject === $first) {
                             $firstCalls['grants']++;
                         } else {
                             $secondCalls['grants']++;
                         }

                         return $hasRole || $subject === $second
                             ? new Authorizations([$permission])
                             : new Authorizations([]);
                     });
        $subjectRoles->expects(self::once())
                     ->method('remove')
                     ->with($first, $role)
                     ->willReturnCallback(function () use (&$hasRole): void {
                         $hasRole = false;
                     });

        $subjectPermissions = $this->createMock(SubjectPermissionRepository::class);
        $subjectPermissions->method('allOf')
                           ->willReturnCallback(function (TestSubject $subject) use (
                               &$firstCalls,
                               &$secondCalls,
                               $first,
                           ): SubjectPermissions {
                               if ($subject === $first) {
                                   $firstCalls['permissions']++;
                               } else {
                                   $secondCalls['permissions']++;
                               }

                               return new SubjectPermissions([]);
                           });

        [$authorizer, $cached] = $this->cachedContext(
            $subjectRoles,
            $subjectPermissions,
            $this->createStub(RolePermissionRepository::class),
        );

        self::assertTrue($authorizer->can($first, ['posts.edit']));
        self::assertTrue($authorizer->can($second, ['posts.edit']));
        self::assertSame(['roles' => 1, 'grants' => 1, 'permissions' => 1], $firstCalls);
        self::assertSame(['roles' => 1, 'grants' => 1, 'permissions' => 1], $secondCalls);

        $cached->subjectRoleRepository()->remove($first, $role);

        self::assertFalse($authorizer->can($first, ['posts.edit']));
        self::assertTrue($authorizer->can($second, ['posts.edit']));
        self::assertSame(['roles' => 2, 'grants' => 2, 'permissions' => 2], $firstCalls);
        self::assertSame(['roles' => 1, 'grants' => 1, 'permissions' => 1], $secondCalls);

        self::assertFalse($authorizer->can($first, ['posts.edit']));
        self::assertTrue($authorizer->can($second, ['posts.edit']));
        self::assertSame(['roles' => 2, 'grants' => 2, 'permissions' => 2], $firstCalls);
        self::assertSame(['roles' => 1, 'grants' => 1, 'permissions' => 1], $secondCalls);
    }

    public function test_creating_and_updating_a_subject_permission_keep_its_projection_warm(): void
    {
        $subject = new TestSubject(1);
        $calls   = $this->persistenceCalls();

        $subjectRoles = $this->createMock(SubjectRoleRepository::class);
        $subjectRoles->method('allOf')
                     ->willReturnCallback(function () use (&$calls): Authorizations {
                         $calls['roles']++;

                         return new Authorizations([]);
                     });
        $subjectRoles->method('grants')
                     ->willReturnCallback(function () use (&$calls): Authorizations {
                         $calls['grants']++;

                         return new Authorizations([]);
                     });

        $subjectPermissions = $this->createMock(SubjectPermissionRepository::class);
        $subjectPermissions->method('allOf')
                           ->willReturnCallback(function () use (&$calls): SubjectPermissions {
                               $calls['permissions']++;

                               return new SubjectPermissions([]);
                           });
        $subjectPermissions->expects(self::once())
                           ->method('create')
                           ->with($subject, new SubjectPermissionSnapshot(10, 'posts.edit'));
        $subjectPermissions->expects(self::once())
                           ->method('update')
                           ->with($subject, new SubjectPermissionSnapshot(10, 'posts.edit', true));

        [$authorizer, $cached] = $this->cachedContext(
            $subjectRoles,
            $subjectPermissions,
            $this->createStub(RolePermissionRepository::class),
        );

        self::assertFalse($authorizer->can($subject, ['posts.edit']));
        self::assertSame(['roles' => 1, 'grants' => 1, 'permissions' => 1], $calls);

        $cached->subjectPermissionRepository()->create($subject, new SubjectPermissionSnapshot(10, 'posts.edit'));

        self::assertTrue($authorizer->can($subject, ['posts.edit']));
        self::assertSame(['roles' => 1, 'grants' => 1, 'permissions' => 1], $calls);

        $cached->subjectPermissionRepository()->update($subject, new SubjectPermissionSnapshot(10, 'posts.edit', true));

        self::assertFalse($authorizer->can($subject, ['posts.edit']));
        self::assertSame(['roles' => 1, 'grants' => 1, 'permissions' => 1], $calls);
    }

    public function test_removing_a_subject_permission_rebuilds_once_then_returns_to_a_warm_cache(): void
    {
        $subject    = new TestSubject(1);
        $permission = new TestPermission(10, 'posts.edit', 'Edit Posts');
        $calls      = $this->persistenceCalls();

        $subjectRoles = $this->createMock(SubjectRoleRepository::class);
        $subjectRoles->method('allOf')
                     ->willReturnCallback(function () use (&$calls): Authorizations {
                         $calls['roles']++;

                         return new Authorizations([]);
                     });
        $subjectRoles->method('grants')
                     ->willReturnCallback(function () use (&$calls): Authorizations {
                         $calls['grants']++;

                         return new Authorizations([]);
                     });

        $subjectPermissions = $this->createMock(SubjectPermissionRepository::class);
        $subjectPermissions->method('allOf')
                           ->willReturnCallback(function () use (&$calls, $permission): SubjectPermissions {
                               $calls['permissions']++;

                               return new SubjectPermissions($calls['permissions'] === 1
                                   ? [TestSubjectPermission::from($permission)]
                                   : []);
                           });
        $subjectPermissions->expects(self::once())
                           ->method('remove')
                           ->with($subject, new SubjectPermissionSnapshot(10, 'posts.edit'));

        [$authorizer, $cached] = $this->cachedContext(
            $subjectRoles,
            $subjectPermissions,
            $this->createStub(RolePermissionRepository::class),
        );

        self::assertTrue($authorizer->can($subject, ['posts.edit']));
        self::assertSame(['roles' => 1, 'grants' => 1, 'permissions' => 1], $calls);

        $cached->subjectPermissionRepository()->remove($subject, new SubjectPermissionSnapshot(10, 'posts.edit'));

        self::assertFalse($authorizer->can($subject, ['posts.edit']));
        self::assertSame(['roles' => 2, 'grants' => 2, 'permissions' => 2], $calls);

        self::assertFalse($authorizer->can($subject, ['posts.edit']));
        self::assertSame(['roles' => 2, 'grants' => 2, 'permissions' => 2], $calls);
    }

    public function test_removing_a_role_permission_invalidates_every_subject_projection_once(): void
    {
        $first       = new TestSubject(1);
        $second      = new TestSubject(2);
        $role        = new TestRole(20, 'editor', 'Editor');
        $permission  = new TestPermission(10, 'posts.edit', 'Edit Posts');
        $firstCalls  = $this->persistenceCalls();
        $secondCalls = $this->persistenceCalls();
        $hasGrant    = true;

        $subjectRoles = $this->createMock(SubjectRoleRepository::class);
        $subjectRoles->method('allOf')
                     ->willReturnCallback(function (TestSubject $subject) use (&$firstCalls, &$secondCalls, $first, $role): Authorizations {
                         if ($subject === $first) {
                             $firstCalls['roles']++;
                         } else {
                             $secondCalls['roles']++;
                         }

                         return new Authorizations([$role]);
                     });
        $subjectRoles->method('grants')
                     ->willReturnCallback(function (TestSubject $subject) use (
                         &$firstCalls,
                         &$secondCalls,
                         $first,
                         $permission,
                         &$hasGrant
                     ): Authorizations {
                         if ($subject === $first) {
                             $firstCalls['grants']++;
                         } else {
                             $secondCalls['grants']++;
                         }

                         return $hasGrant ? new Authorizations([$permission]) : new Authorizations([]);
                     });

        $subjectPermissions = $this->createMock(SubjectPermissionRepository::class);
        $subjectPermissions->method('allOf')
                           ->willReturnCallback(function (TestSubject $subject) use (
                               &$firstCalls,
                               &$secondCalls,
                               $first
                           ): SubjectPermissions {
                               if ($subject === $first) {
                                   $firstCalls['permissions']++;
                               } else {
                                   $secondCalls['permissions']++;
                               }

                               return new SubjectPermissions([]);
                           });

        $rolePermissions = $this->createMock(RolePermissionRepository::class);
        $rolePermissions->expects(self::once())
                        ->method('remove')
                        ->with($role, $permission)
                        ->willReturnCallback(function () use (&$hasGrant): void {
                            $hasGrant = false;
                        });

        [$authorizer, $cached] = $this->cachedContext($subjectRoles, $subjectPermissions, $rolePermissions);

        self::assertTrue($authorizer->can($first, ['posts.edit']));
        self::assertTrue($authorizer->can($second, ['posts.edit']));
        self::assertSame(['roles' => 1, 'grants' => 1, 'permissions' => 1], $firstCalls);
        self::assertSame(['roles' => 1, 'grants' => 1, 'permissions' => 1], $secondCalls);

        $cached->rolePermissionRepository()->remove($role, $permission);

        self::assertFalse($authorizer->can($first, ['posts.edit']));
        self::assertFalse($authorizer->can($second, ['posts.edit']));
        self::assertSame(['roles' => 2, 'grants' => 2, 'permissions' => 2], $firstCalls);
        self::assertSame(['roles' => 2, 'grants' => 2, 'permissions' => 2], $secondCalls);

        self::assertFalse($authorizer->can($first, ['posts.edit']));
        self::assertFalse($authorizer->can($second, ['posts.edit']));
        self::assertSame(['roles' => 2, 'grants' => 2, 'permissions' => 2], $firstCalls);
        self::assertSame(['roles' => 2, 'grants' => 2, 'permissions' => 2], $secondCalls);
    }

    public function test_adding_a_role_permission_invalidates_every_subject_projection_once(): void
    {
        $first       = new TestSubject(1);
        $second      = new TestSubject(2);
        $role        = new TestRole(20, 'editor', 'Editor');
        $permission  = new TestPermission(10, 'posts.edit', 'Edit Posts');
        $firstCalls  = $this->persistenceCalls();
        $secondCalls = $this->persistenceCalls();
        $hasGrant    = false;

        $subjectRoles = $this->createMock(SubjectRoleRepository::class);
        $subjectRoles->method('allOf')
                     ->willReturnCallback(function (TestSubject $subject) use (&$firstCalls, &$secondCalls, $first, $role): Authorizations {
                         if ($subject === $first) {
                             $firstCalls['roles']++;
                         } else {
                             $secondCalls['roles']++;
                         }

                         return new Authorizations([$role]);
                     });
        $subjectRoles->method('grants')
                     ->willReturnCallback(function (TestSubject $subject) use (
                         &$firstCalls,
                         &$secondCalls,
                         $first,
                         $permission,
                         &$hasGrant,
                     ): Authorizations {
                         if ($subject === $first) {
                             $firstCalls['grants']++;
                         } else {
                             $secondCalls['grants']++;
                         }

                         return $hasGrant ? new Authorizations([$permission]) : new Authorizations([]);
                     });

        $subjectPermissions = $this->createMock(SubjectPermissionRepository::class);
        $subjectPermissions->method('allOf')
                           ->willReturnCallback(function (TestSubject $subject) use (
                               &$firstCalls,
                               &$secondCalls,
                               $first,
                           ): SubjectPermissions {
                               if ($subject === $first) {
                                   $firstCalls['permissions']++;
                               } else {
                                   $secondCalls['permissions']++;
                               }

                               return new SubjectPermissions([]);
                           });

        $rolePermissions = $this->createMock(RolePermissionRepository::class);
        $rolePermissions->expects(self::once())
                        ->method('create')
                        ->with($role, $permission)
                        ->willReturnCallback(function () use (&$hasGrant): void {
                            $hasGrant = true;
                        });

        [$authorizer, $cached] = $this->cachedContext($subjectRoles, $subjectPermissions, $rolePermissions);

        self::assertFalse($authorizer->can($first, ['posts.edit']));
        self::assertFalse($authorizer->can($second, ['posts.edit']));
        self::assertSame(['roles' => 1, 'grants' => 1, 'permissions' => 1], $firstCalls);
        self::assertSame(['roles' => 1, 'grants' => 1, 'permissions' => 1], $secondCalls);

        $cached->rolePermissionRepository()->create($role, $permission);

        self::assertTrue($authorizer->can($first, ['posts.edit']));
        self::assertTrue($authorizer->can($second, ['posts.edit']));
        self::assertSame(['roles' => 2, 'grants' => 2, 'permissions' => 2], $firstCalls);
        self::assertSame(['roles' => 2, 'grants' => 2, 'permissions' => 2], $secondCalls);

        self::assertTrue($authorizer->can($first, ['posts.edit']));
        self::assertTrue($authorizer->can($second, ['posts.edit']));
        self::assertSame(['roles' => 2, 'grants' => 2, 'permissions' => 2], $firstCalls);
        self::assertSame(['roles' => 2, 'grants' => 2, 'permissions' => 2], $secondCalls);
    }

    /**
     * @param array{roles: int, grants: int, permissions: int} $calls
     */
    private function cachedAuthorizer(
        TestSubject        $subject,
        Authorizations     $roles,
        Authorizations     $grants,
        SubjectPermissions $permissions,
        array              &$calls,
    ): Authorizer
    {
        $subjectRoles = $this->createMock(SubjectRoleRepository::class);
        $subjectRoles->method('allOf')
                     ->with($subject)
                     ->willReturnCallback(function () use (&$calls, $roles): Authorizations {
                         $calls['roles']++;

                         return $roles;
                     });
        $subjectRoles->method('grants')
                     ->with($subject)
                     ->willReturnCallback(function () use (&$calls, $grants): Authorizations {
                         $calls['grants']++;

                         return $grants;
                     });

        $subjectPermissions = $this->createMock(SubjectPermissionRepository::class);
        $subjectPermissions->method('allOf')
                           ->with($subject)
                           ->willReturnCallback(function () use (&$calls, $permissions): SubjectPermissions {
                               $calls['permissions']++;

                               return $permissions;
                           });

        [$authorizer] = $this->cachedContext(
            $subjectRoles,
            $subjectPermissions,
            $this->createStub(RolePermissionRepository::class),
        );

        return $authorizer;
    }

    /**
     * @return array{Authorizer, CachedRepositories}
     */
    private function cachedContext(
        SubjectRoleRepository       $subjectRoles,
        SubjectPermissionRepository $subjectPermissions,
        RolePermissionRepository    $rolePermissions,
    ): array
    {
        $cached = SentinelCacheFactory::from(
            new InMemoryCache(),
            new CacheSettings(prefix: 'cached-authorizer-cache-flow-test'),
        )->build(
            roles             : $this->createStub(RoleRepository::class),
            permissions       : $this->createStub(PermissionRepository::class),
            rolePermissions   : $rolePermissions,
            subjectRoles      : $subjectRoles,
            subjectPermissions: $subjectPermissions,
        );

        return [new Authorizer(
            new PermissionEntryProvider(
                $cached->subjectPermissionRepository(),
                $cached->subjectRoleRepository(),
            ),
            new RoleEntryProvider($cached->subjectRoleRepository()),
        ),
            $cached];
    }

    /**
     * @return array{roles: int, grants: int, permissions: int}
     */
    private function persistenceCalls(): array
    {
        return ['roles' => 0, 'grants' => 0, 'permissions' => 0];
    }
}
