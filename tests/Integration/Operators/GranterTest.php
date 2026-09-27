<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Tests\Integration\Operators;

use Vaened\Sentinel\Errors\InvalidAuthorization;
use Vaened\Sentinel\Operators\Granter;
use Vaened\Sentinel\Operators\SubjectPermissionSnapshot;
use Vaened\Sentinel\SubjectPermissionState;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemoryPermissionRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemoryRolePermissionRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemoryRoleRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemorySubjectPermissionRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemorySubjectRoleRepository;
use Vaened\Sentinel\Tests\Runtime\TestRole;
use Vaened\Sentinel\Tests\Runtime\TestSubject;
use Vaened\Sentinel\Tests\TestCase;

final class GranterTest extends TestCase
{
    private InMemoryPermissionRepository        $permissions;

    private InMemoryRoleRepository              $roles;

    private InMemoryRolePermissionRepository    $rolePermissions;

    private InMemorySubjectPermissionRepository $subjectPermissions;

    private Granter                             $granter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->permissions        = new InMemoryPermissionRepository();
        $this->roles              = new InMemoryRoleRepository();
        $this->rolePermissions    = new InMemoryRolePermissionRepository();
        $this->subjectPermissions = new InMemorySubjectPermissionRepository();

        $this->granter = new Granter(
            $this->roles,
            $this->permissions,
            new InMemorySubjectRoleRepository($this->rolePermissions),
            $this->subjectPermissions,
            $this->rolePermissions,
        );
    }

    public function test_grant_persists_a_direct_subject_permission(): void
    {
        $subject    = new TestSubject(1);
        $permission = $this->permissions->create('posts.edit', 'Edit Posts');

        $this->granter->grant($subject, $permission);

        self::assertSame(
            SubjectPermissionState::Direct,
            $this->subjectPermissions->lookup($subject, 'posts.edit')->find('posts.edit')?->state(),
        );
    }

    public function test_grant_promotes_a_direct_denial_to_a_direct_permission(): void
    {
        $subject    = new TestSubject(1);
        $permission = $this->permissions->create('posts.edit', 'Edit Posts');
        $this->subjectPermissions->create(
            $subject,
            new SubjectPermissionSnapshot($permission->id(), $permission->code(), true),
        );

        $this->granter->grant($subject, $permission);

        self::assertSame(
            SubjectPermissionState::Direct,
            $this->subjectPermissions->lookup($subject, 'posts.edit')->find('posts.edit')?->state(),
        );
    }

    public function test_grant_persists_a_permission_for_a_role(): void
    {
        $role       = $this->roles->create('editor', 'Editor');
        $permission = $this->permissions->create('posts.edit', 'Edit Posts');

        $this->granter->grant($role, $permission);

        self::assertTrue($this->rolePermissions->lookup($role, 'posts.edit')->hasCode('posts.edit'));
    }

    public function test_grant_assigns_a_scoped_role_to_a_subject_in_the_same_scope(): void
    {
        $scope        = new TestSubject(2);
        $subject      = new TestSubject(1, $scope);
        $roles        = new InMemoryRoleRepository();
        $role         = $roles->create('editor', 'Editor', scope: $scope);
        $rolePerms    = new InMemoryRolePermissionRepository();
        $subjectRoles = new InMemorySubjectRoleRepository($rolePerms);
        $granter      = new Granter(
            $roles,
            new InMemoryPermissionRepository(),
            $subjectRoles,
            new InMemorySubjectPermissionRepository(),
            $rolePerms,
        );

        $granter->grant($subject, $role);

        self::assertTrue($subjectRoles->lookup($subject, 'editor')->hasCode('editor'));
    }

    public function test_grant_assigns_a_global_role_to_a_scoped_subject(): void
    {
        $scope        = new TestSubject(2);
        $subject      = new TestSubject(1, $scope);
        $roles        = new InMemoryRoleRepository();
        $role         = $roles->create('editor', 'Editor');
        $rolePerms    = new InMemoryRolePermissionRepository();
        $subjectRoles = new InMemorySubjectRoleRepository($rolePerms);
        $granter      = new Granter(
            $roles,
            new InMemoryPermissionRepository(),
            $subjectRoles,
            new InMemorySubjectPermissionRepository(),
            $rolePerms,
        );

        $granter->grant($subject, $role);

        self::assertTrue($subjectRoles->lookup($subject, 'editor')->hasCode('editor'));
    }

    public function test_grant_assigns_a_global_role_to_an_unscoped_subject(): void
    {
        $subject      = new TestSubject(1);
        $roles        = new InMemoryRoleRepository();
        $role         = $roles->create('editor', 'Editor');
        $rolePerms    = new InMemoryRolePermissionRepository();
        $subjectRoles = new InMemorySubjectRoleRepository($rolePerms);
        $granter      = new Granter(
            $roles,
            new InMemoryPermissionRepository(),
            $subjectRoles,
            new InMemorySubjectPermissionRepository(),
            $rolePerms,
        );

        $granter->grant($subject, $role);

        self::assertTrue($subjectRoles->lookup($subject, 'editor')->hasCode('editor'));
    }

    public function test_grant_rejects_a_scoped_role_for_an_unscoped_subject(): void
    {
        $scope        = new TestSubject(2);
        $subject      = new TestSubject(1);
        $roles        = new InMemoryRoleRepository();
        $role         = $roles->create('editor', 'Editor', scope: $scope);
        $rolePerms    = new InMemoryRolePermissionRepository();
        $subjectRoles = new InMemorySubjectRoleRepository($rolePerms);
        $granter      = new Granter(
            $roles,
            new InMemoryPermissionRepository(),
            $subjectRoles,
            new InMemorySubjectPermissionRepository(),
            $rolePerms,
        );

        $this->expectException(InvalidAuthorization::class);
        $this->expectExceptionMessage(
            'Role [editor] scoped to [Vaened\\Sentinel\\Tests\\Runtime\\TestSubject:2] cannot be assigned to subject [Vaened\\Sentinel\\Tests\\Runtime\\TestSubject:1].',
        );

        $granter->grant($subject, $role);
    }

    public function test_grant_rejects_a_scoped_role_from_another_scope(): void
    {
        $subjectScope = new TestSubject(2);
        $roleScope    = new TestSubject(3);
        $subject      = new TestSubject(1, $subjectScope);
        $roles        = new InMemoryRoleRepository();
        $role         = $roles->create('editor', 'Editor', scope: $roleScope);
        $rolePerms    = new InMemoryRolePermissionRepository();
        $subjectRoles = new InMemorySubjectRoleRepository($rolePerms);
        $granter      = new Granter(
            $roles,
            new InMemoryPermissionRepository(),
            $subjectRoles,
            new InMemorySubjectPermissionRepository(),
            $rolePerms,
        );

        $this->expectException(InvalidAuthorization::class);
        $this->expectExceptionMessage(
            'Role [editor] scoped to [Vaened\\Sentinel\\Tests\\Runtime\\TestSubject:3] cannot be assigned to subject [Vaened\\Sentinel\\Tests\\Runtime\\TestSubject:1].',
        );

        $granter->grant($subject, $role);
    }

    public function test_grant_uses_the_persisted_role_scope(): void
    {
        $scope        = new TestSubject(2);
        $subject      = new TestSubject(1);
        $roles        = new InMemoryRoleRepository();
        $stored       = $roles->create('editor', 'Editor', scope: $scope);
        $forged       = new TestRole($stored->id(), $stored->code(), $stored->name());
        $rolePerms    = new InMemoryRolePermissionRepository();
        $subjectRoles = new InMemorySubjectRoleRepository($rolePerms);
        $granter      = new Granter(
            $roles,
            new InMemoryPermissionRepository(),
            $subjectRoles,
            new InMemorySubjectPermissionRepository(),
            $rolePerms,
        );

        $this->expectException(InvalidAuthorization::class);
        $this->expectExceptionMessage(
            'Role [editor] scoped to [Vaened\\Sentinel\\Tests\\Runtime\\TestSubject:2] cannot be assigned to subject [Vaened\\Sentinel\\Tests\\Runtime\\TestSubject:1].',
        );

        $granter->grant($subject, $forged);
    }
}
