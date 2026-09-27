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
use Vaened\Sentinel\Errors\RoleNotFound;
use Vaened\Sentinel\Operators\Revoker;
use Vaened\Sentinel\Permissions;
use Vaened\Sentinel\Repositories\PermissionRepository;
use Vaened\Sentinel\Repositories\RolePermissionRepository;
use Vaened\Sentinel\Repositories\RoleRepository;
use Vaened\Sentinel\Repositories\SubjectPermissionRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;
use Vaened\Sentinel\Roles;
use Vaened\Sentinel\SubjectPermissions;
use Vaened\Sentinel\Tests\Runtime\TestPermission;
use Vaened\Sentinel\Tests\Runtime\TestRole;
use Vaened\Sentinel\Tests\Runtime\TestSubject;
use Vaened\Sentinel\Tests\Runtime\TestSubjectPermission;
use Vaened\Sentinel\Tests\TestCase;

final class RevokerTest extends TestCase
{
    public function test_revoke_removes_an_assigned_direct_subject_permission(): void
    {
        $subject     = new TestSubject(1);
        $permission  = new TestPermission(1, 'posts.edit', 'Edit Posts');
        $permissions = $this->createMock(PermissionRepository::class);
        $permissions->method('lookup')->with('posts.edit')->willReturn(new Permissions([$permission]));

        $assignments = $this->createMock(SubjectPermissionRepository::class);
        $assignments->method('lookup')
                    ->with($subject, 'posts.edit')
                    ->willReturn(new SubjectPermissions([TestSubjectPermission::from($permission)]));
        $assignments->expects(self::once())
                    ->method('remove')
                    ->with($subject, self::callback(static fn($assignment): bool => $assignment->code() === 'posts.edit'));

        $this->revoker(
            permissions       : $permissions,
            subjectPermissions: $assignments,
        )->revoke($subject, $permission);
    }

    public function test_revoke_skips_an_unassigned_subject_permission(): void
    {
        $subject     = new TestSubject(1);
        $permission  = new TestPermission(1, 'posts.edit', 'Edit Posts');
        $permissions = $this->createMock(PermissionRepository::class);
        $permissions->method('lookup')->with('posts.edit')->willReturn(new Permissions([$permission]));

        $assignments = $this->createMock(SubjectPermissionRepository::class);
        $assignments->method('lookup')->with($subject, 'posts.edit')->willReturn(new SubjectPermissions([]));
        $assignments->expects(self::never())->method('remove');

        $this->revoker(
            permissions       : $permissions,
            subjectPermissions: $assignments,
        )->revoke($subject, $permission);
    }

    public function test_revoke_propagates_a_permission_catalog_failure(): void
    {
        $permission  = new TestPermission(1, 'posts.edit', 'Edit Posts');
        $permissions = $this->createMock(PermissionRepository::class);
        $permissions->method('lookup')->willThrowException(new RuntimeException('DB connection lost'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DB connection lost');

        $this->revoker(permissions: $permissions)->revoke(new TestSubject(1), $permission);
    }

    public function test_revoke_uses_the_persisted_role_instance_for_removal(): void
    {
        $subject   = new TestSubject(1);
        $requested = new TestRole(1, 'editor', 'Editor');
        $persisted = new TestRole(1, 'editor', 'Stored Editor');
        $roles     = $this->createMock(RoleRepository::class);
        $roles->method('match')->with('editor')->willReturn(new Roles([$persisted]));

        $assignments = $this->createMock(SubjectRoleRepository::class);
        $assignments->method('lookup')->with($subject, 'editor')->willReturn(new Roles([$persisted]));
        $assignments->expects(self::once())->method('remove')->with($subject, $persisted);

        $this->revoker(
            roles       : $roles,
            subjectRoles: $assignments,
        )->revoke($subject, $requested);
    }

    public function test_revoke_rejects_a_missing_role_without_loading_assignments(): void
    {
        $role  = new TestRole(1, 'editor', 'Editor');
        $roles = $this->createMock(RoleRepository::class);
        $roles->method('match')->with('editor')->willReturn(new Roles([]));

        $assignments = $this->createMock(SubjectRoleRepository::class);
        $assignments->expects(self::never())->method('lookup');
        $assignments->expects(self::never())->method('remove');

        $this->expectException(RoleNotFound::class);

        $this->revoker(
            roles       : $roles,
            subjectRoles: $assignments,
        )->revoke(new TestSubject(1), $role);
    }

    public function test_revoke_skips_an_unassigned_role(): void
    {
        $subject = new TestSubject(1);
        $role    = new TestRole(1, 'editor', 'Editor');
        $roles   = $this->createMock(RoleRepository::class);
        $roles->method('match')->with('editor')->willReturn(new Roles([$role]));

        $assignments = $this->createMock(SubjectRoleRepository::class);
        $assignments->method('lookup')->with($subject, 'editor')->willReturn(new Roles([]));
        $assignments->expects(self::never())->method('remove');

        $this->revoker(
            roles       : $roles,
            subjectRoles: $assignments,
        )->revoke($subject, $role);
    }

    public function test_revoke_removes_an_assigned_role_permission(): void
    {
        $role        = new TestRole(1, 'editor', 'Editor');
        $permission  = new TestPermission(1, 'posts.edit', 'Edit Posts');
        $permissions = $this->createMock(PermissionRepository::class);
        $permissions->method('lookup')->with('posts.edit')->willReturn(new Permissions([$permission]));

        $assignments = $this->createMock(RolePermissionRepository::class);
        $assignments->method('lookup')->with($role, 'posts.edit')->willReturn(new Permissions([$permission]));
        $assignments->expects(self::once())->method('remove')->with($role, $permission);

        $this->revoker(
            permissions    : $permissions,
            rolePermissions: $assignments,
        )->revoke($role, $permission);
    }

    public function test_revoke_skips_an_unassigned_role_permission(): void
    {
        $role        = new TestRole(1, 'editor', 'Editor');
        $permission  = new TestPermission(1, 'posts.edit', 'Edit Posts');
        $permissions = $this->createMock(PermissionRepository::class);
        $permissions->method('lookup')->with('posts.edit')->willReturn(new Permissions([$permission]));

        $assignments = $this->createMock(RolePermissionRepository::class);
        $assignments->method('lookup')->with($role, 'posts.edit')->willReturn(new Permissions([]));
        $assignments->expects(self::never())->method('remove');

        $this->revoker(
            permissions    : $permissions,
            rolePermissions: $assignments,
        )->revoke($role, $permission);
    }

    public function test_purge_removes_all_subject_authorizations_without_catalog_lookups(): void
    {
        $subject = new TestSubject(1);

        $roles = $this->createMock(SubjectRoleRepository::class);
        $roles->expects(self::once())->method('purge')->with($subject);

        $permissions = $this->createMock(SubjectPermissionRepository::class);
        $permissions->expects(self::once())->method('purge')->with($subject);

        $this->revoker(
            subjectRoles      : $roles,
            subjectPermissions: $permissions,
        )->purge($subject);
    }

    private function revoker(
        RoleRepository|null              $roles = null,
        PermissionRepository|null        $permissions = null,
        SubjectRoleRepository|null       $subjectRoles = null,
        SubjectPermissionRepository|null $subjectPermissions = null,
        RolePermissionRepository|null    $rolePermissions = null,
    ): Revoker
    {
        return new Revoker(
            $roles ?? $this->createStub(RoleRepository::class),
            $permissions ?? $this->createStub(PermissionRepository::class),
            $subjectRoles ?? $this->createStub(SubjectRoleRepository::class),
            $subjectPermissions ?? $this->createStub(SubjectPermissionRepository::class),
            $rolePermissions ?? $this->createStub(RolePermissionRepository::class),
        );
    }
}
