<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Tests\Unit\Operators;

use RuntimeException;
use Vaened\Sentinel\Errors\InvalidAuthorization;
use Vaened\Sentinel\Errors\RoleNotFound;
use Vaened\Sentinel\Operators\Granter;
use Vaened\Sentinel\Operators\SubjectPermissionSnapshot;
use Vaened\Sentinel\Permissions;
use Vaened\Sentinel\Repositories\PermissionRepository;
use Vaened\Sentinel\Repositories\RolePermissionRepository;
use Vaened\Sentinel\Repositories\RoleRepository;
use Vaened\Sentinel\Repositories\SubjectPermissionRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;
use Vaened\Sentinel\Roles;
use Vaened\Sentinel\SubjectPermissions;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemorySubjectPermissionRepository;
use Vaened\Sentinel\Tests\Runtime\TestPermission;
use Vaened\Sentinel\Tests\Runtime\TestRole;
use Vaened\Sentinel\Tests\Runtime\TestSubject;
use Vaened\Sentinel\Tests\Runtime\TestSubjectPermission;
use Vaened\Sentinel\Tests\TestCase;

final class GranterTest extends TestCase
{
    public function test_grant_resolves_a_permission_before_creating_a_subject_assignment(): void
    {
        $permission = new TestPermission(1, 'posts.edit', 'Edit Posts');
        $calls      = [];

        $permissions = $this->createMock(PermissionRepository::class);
        $permissions->method('lookup')
                    ->willReturnCallback(function () use (&$calls, $permission): Permissions {
                        $calls[] = 'catalog.lookup';

                        return new Permissions([$permission]);
                    });

        $assignments = $this->createMock(SubjectPermissionRepository::class);
        $assignments->method('lookup')
                    ->willReturnCallback(function () use (&$calls): SubjectPermissions {
                        $calls[] = 'assignment.lookup';

                        return new SubjectPermissions([]);
                    });
        $assignments->expects(self::once())
                    ->method('create')
                    ->willReturnCallback(function () use (&$calls): void {
                        $calls[] = 'assignment.create';
                    });

        $this->granter(
            permissions       : $permissions,
            subjectPermissions: $assignments,
        )->grant(new TestSubject(1), $permission);

        self::assertSame(['catalog.lookup', 'assignment.lookup', 'assignment.create'], $calls);
    }

    public function test_grant_propagates_a_permission_catalog_failure(): void
    {
        $permission  = new TestPermission(1, 'posts.edit', 'Edit Posts');
        $permissions = $this->createMock(PermissionRepository::class);
        $permissions->method('lookup')
                    ->willThrowException(new RuntimeException('DB connection lost'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DB connection lost');

        $this->granter(permissions: $permissions)->grant(new TestSubject(1), $permission);
    }

    public function test_grant_skips_an_existing_direct_subject_permission(): void
    {
        $subject     = new TestSubject(1);
        $permission  = new TestPermission(1, 'posts.edit', 'Edit Posts');
        $permissions = $this->createMock(PermissionRepository::class);
        $permissions->method('lookup')->with('posts.edit')->willReturn(new Permissions([$permission]));

        $assignments = $this->createMock(SubjectPermissionRepository::class);
        $assignments->method('lookup')
                    ->with($subject, 'posts.edit')
                    ->willReturn(new SubjectPermissions([TestSubjectPermission::from($permission)]));
        $assignments->expects(self::never())->method('create');
        $assignments->expects(self::never())->method('update');

        $this->granter(
            permissions       : $permissions,
            subjectPermissions: $assignments,
        )->grant($subject, $permission);
    }

    public function test_grant_promotes_a_direct_denial_with_an_update(): void
    {
        $subject     = new TestSubject(1);
        $permission  = new TestPermission(1, 'posts.edit', 'Edit Posts');
        $permissions = $this->createMock(PermissionRepository::class);
        $permissions->method('lookup')->with('posts.edit')->willReturn(new Permissions([$permission]));

        $assignments = $this->createMock(SubjectPermissionRepository::class);
        $assignments->method('lookup')
                    ->with($subject, 'posts.edit')
                    ->willReturn(new SubjectPermissions([TestSubjectPermission::from($permission, true)]));
        $assignments->expects(self::never())->method('create');
        $assignments->expects(self::once())
                    ->method('update')
                    ->with($subject, self::callback(static fn($assignment): bool => !$assignment->state()->isDenied()));

        $this->granter(
            permissions       : $permissions,
            subjectPermissions: $assignments,
        )->grant($subject, $permission);
    }

    public function test_grant_skips_a_permission_already_inherited_from_a_role(): void
    {
        $subject     = new TestSubject(1);
        $permission  = new TestPermission(1, 'posts.edit', 'Edit Posts');
        $permissions = $this->createMock(PermissionRepository::class);
        $permissions->method('lookup')->with('posts.edit')->willReturn(new Permissions([$permission]));

        $assignments = $this->createMock(SubjectPermissionRepository::class);
        $assignments->method('lookup')->with($subject, 'posts.edit')->willReturn(new SubjectPermissions([]));
        $assignments->expects(self::never())->method('create');
        $assignments->expects(self::never())->method('update');

        $roles = $this->createMock(SubjectRoleRepository::class);
        $roles->expects(self::once())
              ->method('grants')
              ->with($subject, ['posts.edit'])
              ->willReturn(new Permissions([$permission]));

        $this->granter(
            permissions       : $permissions,
            subjectRoles      : $roles,
            subjectPermissions: $assignments,
        )->grant($subject, $permission);
    }

    public function test_grant_uses_the_persisted_role_instance_for_assignment(): void
    {
        $subject   = new TestSubject(1);
        $requested = new TestRole(1, 'editor', 'Editor');
        $persisted = new TestRole(1, 'editor', 'Stored Editor');
        $roles     = $this->createMock(RoleRepository::class);
        $roles->expects(self::once())
              ->method('match')
              ->with('editor')
              ->willReturn(new Roles([$persisted]));

        $assignments = $this->createMock(SubjectRoleRepository::class);
        $assignments->expects(self::once())
                    ->method('lookup')
                    ->with($subject, 'editor')
                    ->willReturn(new Roles([]));
        $assignments->expects(self::once())
                    ->method('create')
                    ->with($subject, $persisted);

        $this->granter(
            roles       : $roles,
            subjectRoles: $assignments,
        )->grant($subject, $requested);
    }

    public function test_grant_rejects_a_missing_role_without_mutating_assignments(): void
    {
        $role  = new TestRole(1, 'editor', 'Editor');
        $roles = $this->createMock(RoleRepository::class);
        $roles->expects(self::once())
              ->method('match')
              ->with('editor')
              ->willReturn(new Roles([]));

        $assignments = $this->createMock(SubjectRoleRepository::class);
        $assignments->expects(self::never())->method('lookup');
        $assignments->expects(self::never())->method('create');

        $this->expectException(RoleNotFound::class);

        $this->granter(
            roles       : $roles,
            subjectRoles: $assignments,
        )->grant(new TestSubject(1), $role);
    }

    public function test_grant_rejects_a_role_with_an_incompatible_scope_before_loading_assignments(): void
    {
        $subject = new TestSubject(1, new TestSubject(2));
        $role    = new TestRole(1, 'editor', 'Editor', scope: new TestSubject(3));
        $roles   = $this->createMock(RoleRepository::class);
        $roles->method('match')->with('editor')->willReturn(new Roles([$role]));

        $assignments = $this->createMock(SubjectRoleRepository::class);
        $assignments->expects(self::never())->method('lookup');
        $assignments->expects(self::never())->method('create');

        $this->expectException(InvalidAuthorization::class);

        $this->granter(
            roles       : $roles,
            subjectRoles: $assignments,
        )->grant($subject, $role);
    }

    public function test_grant_validates_multiple_role_permissions_in_a_single_batch(): void
    {
        $scope       = new TestSubject(2);
        $subject     = new TestSubject(1, $scope);
        $editor      = new TestRole(10, 'editor', 'Editor', scope: $scope);
        $publisher   = new TestRole(11, 'publisher', 'Publisher', scope: $scope);
        $edit        = new TestPermission(20, 'posts.edit', 'Edit Posts');
        $publish     = new TestPermission(21, 'posts.publish', 'Publish Posts');
        $roles       = $this->createMock(RoleRepository::class);
        $assignments = $this->createMock(SubjectRoleRepository::class);
        $permissions = new InMemorySubjectPermissionRepository();

        $roles->expects(self::once())
              ->method('match')
              ->with('editor', 'publisher')
              ->willReturn(new Roles([$editor, $publisher]));

        $assignments->expects(self::once())
                    ->method('lookup')
                    ->with($subject, 'editor', 'publisher')
                    ->willReturn(new Roles([]));
        $assignments->expects(self::once())
                    ->method('create')
                    ->with($subject, $editor, $publisher);

        $rolePermissions = $this->createMock(RolePermissionRepository::class);
        $rolePermissions->expects(self::once())
                        ->method('grants')
                        ->with($editor, $publisher)
                        ->willReturn(new Permissions([$edit, $publish]));
        $rolePermissions->expects(self::never())->method('allOf');

        $permissions->create(
            $scope,
            SubjectPermissionSnapshot::from($edit),
            SubjectPermissionSnapshot::from($publish),
        );

        $this->granter(
            roles             : $roles,
            subjectRoles      : $assignments,
            subjectPermissions: $permissions,
            rolePermissions   : $rolePermissions,
        )->grant($subject, $editor, $publisher);
    }

    public function test_grant_skips_an_assigned_role(): void
    {
        $subject = new TestSubject(1);
        $role    = new TestRole(1, 'editor', 'Editor');
        $roles   = $this->createMock(RoleRepository::class);
        $roles->method('match')->with('editor')->willReturn(new Roles([$role]));

        $assignments = $this->createMock(SubjectRoleRepository::class);
        $assignments->method('lookup')->with($subject, 'editor')->willReturn(new Roles([$role]));
        $assignments->expects(self::never())->method('create');

        $this->granter(
            roles       : $roles,
            subjectRoles: $assignments,
        )->grant($subject, $role);
    }

    public function test_grant_skips_an_assigned_role_permission(): void
    {
        $role        = new TestRole(1, 'editor', 'Editor');
        $permission  = new TestPermission(1, 'posts.edit', 'Edit Posts');
        $permissions = $this->createMock(PermissionRepository::class);
        $permissions->method('lookup')->with('posts.edit')->willReturn(new Permissions([$permission]));

        $assignments = $this->createMock(RolePermissionRepository::class);
        $assignments->method('lookup')->with($role, 'posts.edit')->willReturn(new Permissions([$permission]));
        $assignments->expects(self::never())->method('create');

        $this->granter(
            permissions    : $permissions,
            rolePermissions: $assignments,
        )->grant($role, $permission);
    }

    private function granter(
        RoleRepository|null              $roles = null,
        PermissionRepository|null        $permissions = null,
        SubjectRoleRepository|null       $subjectRoles = null,
        SubjectPermissionRepository|null $subjectPermissions = null,
        RolePermissionRepository|null    $rolePermissions = null,
    ): Granter
    {
        $roles              ??= $this->createStub(RoleRepository::class);
        $permissions        ??= $this->createStub(PermissionRepository::class);
        $subjectRoles       ??= $this->createStub(SubjectRoleRepository::class);
        $subjectPermissions ??= $this->createStub(SubjectPermissionRepository::class);
        $rolePermissions    ??= $this->createStub(RolePermissionRepository::class);

        return new Granter(
            $roles,
            $permissions,
            $subjectRoles,
            $subjectPermissions,
            $rolePermissions,
            $this->createScopeBoundary($subjectPermissions, $subjectRoles),
        );
    }
}
