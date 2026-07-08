<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Tests\Unit\Projection;

use Vaened\Sentinel\Permissions;
use Vaened\Sentinel\Projection\SubjectAuthorizationProjector;
use Vaened\Sentinel\Repositories\SubjectPermissionRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;
use Vaened\Sentinel\Roles;
use Vaened\Sentinel\SubjectPermissions;
use Vaened\Sentinel\Tests\Runtime\TestPermission;
use Vaened\Sentinel\Tests\Runtime\TestRole;
use Vaened\Sentinel\Tests\Runtime\TestSubject;
use Vaened\Sentinel\Tests\Runtime\TestSubjectPermission;
use Vaened\Sentinel\Tests\TestCase;

final class SubjectAuthorizationProjectorTest extends TestCase
{
    public function test_project_returns_an_empty_projection_when_the_subject_has_no_roles_or_permissions(): void
    {
        $roles = $this->createMock(SubjectRoleRepository::class);
        $permissions = $this->createMock(SubjectPermissionRepository::class);
        $subject = new TestSubject(1);

        $roles->expects(self::once())
            ->method('allOf')
            ->with($subject)
            ->willReturn(new Roles([]));

        $roles->expects(self::once())
            ->method('grants')
            ->with($subject)
            ->willReturn(new Permissions([]));

        $permissions->expects(self::once())
            ->method('allOf')
            ->with($subject)
            ->willReturn(new SubjectPermissions([]));

        $projection = new SubjectAuthorizationProjector($roles, $permissions)->project($subject);

        self::assertSame([
            'roles' => [],
            'permissions' => [],
        ], $projection->toArray());
    }

    public function test_project_returns_flat_roles_and_direct_permissions(): void
    {
        $roles = $this->createMock(SubjectRoleRepository::class);
        $permissions = $this->createMock(SubjectPermissionRepository::class);
        $subject = new TestSubject(1);

        $roles->expects(self::once())
            ->method('allOf')
            ->with($subject)
            ->willReturn(new Roles([
                new TestRole(10, 'admin', 'Admin'),
                new TestRole(20, 'editor', 'Editor'),
            ]));

        $roles->expects(self::once())
            ->method('grants')
            ->with($subject)
            ->willReturn(new Permissions([]));

        $permissions->expects(self::once())
            ->method('allOf')
            ->with($subject)
            ->willReturn(new SubjectPermissions([
                new TestSubjectPermission(100, 'posts.edit', 'Edit Posts'),
                new TestSubjectPermission(200, 'posts.delete', 'Delete Posts', denied: true),
            ]));

        $projection = new SubjectAuthorizationProjector($roles, $permissions)->project($subject);

        self::assertSame([
            'roles' => ['admin', 'editor'],
            'permissions' => [
                'posts.edit' => true,
                'posts.delete' => false,
            ],
        ], $projection->toArray());
    }

    public function test_project_completes_missing_permissions_from_inherited_grants(): void
    {
        $roles = $this->createMock(SubjectRoleRepository::class);
        $permissions = $this->createMock(SubjectPermissionRepository::class);
        $subject = new TestSubject(1);

        $roles->expects(self::once())
            ->method('allOf')
            ->with($subject)
            ->willReturn(new Roles([
                new TestRole(10, 'admin', 'Admin'),
            ]));

        $roles->expects(self::once())
            ->method('grants')
            ->with($subject)
            ->willReturn(new Permissions([
                new TestPermission(100, 'posts.edit', 'Edit Posts'),
                new TestPermission(200, 'posts.publish', 'Publish Posts'),
            ]));

        $permissions->expects(self::once())
            ->method('allOf')
            ->with($subject)
            ->willReturn(new SubjectPermissions([
                new TestSubjectPermission(300, 'users.delete', 'Delete Users'),
            ]));

        $projection = new SubjectAuthorizationProjector($roles, $permissions)->project($subject);

        self::assertSame([
            'roles' => ['admin'],
            'permissions' => [
                'users.delete' => true,
                'posts.edit' => true,
                'posts.publish' => true,
            ],
        ], $projection->toArray());
    }

    public function test_project_keeps_direct_permissions_when_the_same_code_is_inherited(): void
    {
        $roles = $this->createMock(SubjectRoleRepository::class);
        $permissions = $this->createMock(SubjectPermissionRepository::class);
        $subject = new TestSubject(1);

        $roles->expects(self::once())
            ->method('allOf')
            ->with($subject)
            ->willReturn(new Roles([
                new TestRole(10, 'admin', 'Admin'),
            ]));

        $roles->expects(self::once())
            ->method('grants')
            ->with($subject)
            ->willReturn(new Permissions([
                new TestPermission(100, 'posts.edit', 'Edit Posts'),
                new TestPermission(200, 'posts.publish', 'Publish Posts'),
                new TestPermission(300, 'posts.publish', 'Publish Posts'),
            ]));

        $permissions->expects(self::once())
            ->method('allOf')
            ->with($subject)
            ->willReturn(new SubjectPermissions([
                new TestSubjectPermission(400, 'posts.edit', 'Edit Posts', denied: true),
            ]));

        $projection = new SubjectAuthorizationProjector($roles, $permissions)->project($subject);

        self::assertSame([
            'roles' => ['admin'],
            'permissions' => [
                'posts.edit' => false,
                'posts.publish' => true,
            ],
        ], $projection->toArray());
    }
}
