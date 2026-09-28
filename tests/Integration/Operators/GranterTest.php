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
use Vaened\Sentinel\Propagation\DirectScopePropagationPolicy;
use Vaened\Sentinel\Propagation\ScopePropagationPolicy;
use Vaened\Sentinel\Propagation\TransitiveScopePropagationPolicy;
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

    private InMemorySubjectRoleRepository       $subjectRoles;

    private Granter                             $granter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->permissions        = new InMemoryPermissionRepository();
        $this->roles              = new InMemoryRoleRepository();
        $this->rolePermissions    = new InMemoryRolePermissionRepository();
        $this->subjectPermissions = new InMemorySubjectPermissionRepository();
        $this->subjectRoles       = new InMemorySubjectRoleRepository($this->rolePermissions);

        $this->granter = new Granter(
            $this->roles,
            $this->permissions,
            $this->subjectRoles,
            $this->subjectPermissions,
            $this->rolePermissions,
            $this->createScopeBoundary($this->subjectPermissions, $this->subjectRoles),
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
        $granter      = $this->createGranter($roles, $subjectRoles, $rolePerms);

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
        $granter      = $this->createGranter($roles, $subjectRoles, $rolePerms);

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
        $granter      = $this->createGranter($roles, $subjectRoles, $rolePerms);

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
        $granter      = $this->createGranter($roles, $subjectRoles, $rolePerms);

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
        $granter      = $this->createGranter($roles, $subjectRoles, $rolePerms);

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
        $granter      = $this->createGranter($roles, $subjectRoles, $rolePerms);

        $this->expectException(InvalidAuthorization::class);
        $this->expectExceptionMessage(
            'Role [editor] scoped to [Vaened\\Sentinel\\Tests\\Runtime\\TestSubject:2] cannot be assigned to subject [Vaened\\Sentinel\\Tests\\Runtime\\TestSubject:1].',
        );

        $granter->grant($subject, $forged);
    }

    public function test_grant_rejects_a_subject_permission_not_allowed_by_its_scope(): void
    {
        $scope      = new TestSubject(2);
        $subject    = new TestSubject(1, $scope);
        $permission = $this->permissions->create('posts.edit', 'Edit Posts');

        try {
            $this->granter->grant($subject, $permission);
            self::fail('Expected a permission that exceeds the subject scope to be rejected.');
        } catch (InvalidAuthorization) {
            self::assertTrue($this->subjectPermissions->allOf($subject)->isEmpty());
        }
    }

    public function test_grant_allows_a_subject_permission_allowed_by_its_scope(): void
    {
        $scope      = new TestSubject(2);
        $subject    = new TestSubject(1, $scope);
        $permission = $this->permissions->create('posts.edit', 'Edit Posts');
        $this->subjectPermissions->create(
            $scope,
            new SubjectPermissionSnapshot($permission->id(), $permission->code()),
        );

        $this->granter->grant($subject, $permission);

        self::assertSame(
            SubjectPermissionState::Direct,
            $this->subjectPermissions->lookup($subject, $permission->code())->find($permission->code())?->state(),
        );
    }

    public function test_grant_rejects_a_subject_permission_not_allowed_by_an_ancestor_scope(): void
    {
        $ancestor   = new TestSubject(3);
        $scope      = new TestSubject(2, $ancestor);
        $subject    = new TestSubject(1, $scope);
        $permission = $this->permissions->create('posts.edit', 'Edit Posts');
        $this->subjectPermissions->create(
            $scope,
            new SubjectPermissionSnapshot($permission->id(), $permission->code()),
        );

        try {
            $this->granter->grant($subject, $permission);
            self::fail('Expected a permission that exceeds an ancestor scope to be rejected.');
        } catch (InvalidAuthorization) {
            self::assertTrue($this->subjectPermissions->allOf($subject)->isEmpty());
        }
    }

    public function test_grant_with_direct_scope_propagation_ignores_an_ancestor_for_a_subject_permission(): void
    {
        $organization = new TestSubject(3);
        $team         = new TestSubject(2, $organization);
        $subject      = new TestSubject(1, $team);
        $permission   = $this->permissions->create('posts.edit', 'Edit Posts');
        $granter      = $this->granter(new DirectScopePropagationPolicy());

        $this->subjectPermissions->create($team, SubjectPermissionSnapshot::from($permission));

        $granter->grant($subject, $permission);

        self::assertSame(
            SubjectPermissionState::Direct,
            $this->subjectPermissions->lookup($subject, $permission->code())->find($permission->code())?->state(),
        );
    }

    public function test_grant_with_direct_scope_propagation_ignores_an_ancestor_for_a_role_permission(): void
    {
        $organization = new TestSubject(3);
        $team         = new TestSubject(2, $organization);
        $role         = $this->roles->create('editor', 'Editor', scope: $team);
        $permission   = $this->permissions->create('posts.edit', 'Edit Posts');
        $granter      = $this->granter(new DirectScopePropagationPolicy());

        $this->subjectPermissions->create($team, SubjectPermissionSnapshot::from($permission));

        $granter->grant($role, $permission);

        self::assertTrue($this->rolePermissions->lookup($role, $permission->code())->hasCode($permission->code()));
    }

    public function test_grant_with_direct_scope_propagation_ignores_an_ancestor_for_an_assigned_role(): void
    {
        $organization = new TestSubject(3);
        $team         = new TestSubject(2, $organization);
        $subject      = new TestSubject(1, $team);
        $role         = $this->roles->create('editor', 'Editor', scope: $team);
        $permission   = $this->permissions->create('posts.edit', 'Edit Posts');
        $granter      = $this->granter(new DirectScopePropagationPolicy());

        $this->subjectPermissions->create($team, SubjectPermissionSnapshot::from($permission));
        $this->rolePermissions->create($role, $permission);

        $granter->grant($subject, $role);

        self::assertTrue($this->subjectRoles->lookup($subject, $role->code())->hasCode($role->code()));
    }

    public function test_grant_rejects_a_role_permission_not_allowed_by_the_roles_scope(): void
    {
        $scope      = new TestSubject(2);
        $role       = $this->roles->create('editor', 'Editor', scope: $scope);
        $permission = $this->permissions->create('posts.edit', 'Edit Posts');

        try {
            $this->granter->grant($role, $permission);
            self::fail('Expected a permission that exceeds the role scope to be rejected.');
        } catch (InvalidAuthorization) {
            self::assertTrue($this->rolePermissions->allOf($role)->isEmpty());
        }
    }

    public function test_grant_rejects_a_role_with_a_permission_not_allowed_by_the_subjects_scope(): void
    {
        $scope      = new TestSubject(2);
        $subject    = new TestSubject(1, $scope);
        $role       = $this->roles->create('editor', 'Editor', scope: $scope);
        $permission = $this->permissions->create('posts.edit', 'Edit Posts');
        $this->rolePermissions->create($role, $permission);

        try {
            $this->granter->grant($subject, $role);
            self::fail('Expected a role that exceeds the subject scope to be rejected.');
        } catch (InvalidAuthorization) {
            self::assertTrue($this->subjectRoles->allOf($subject)->isEmpty());
        }
    }

    public function test_grant_does_not_partially_assign_roles_when_one_roles_permission_exceeds_scope(): void
    {
        $scope     = new TestSubject(2);
        $subject   = new TestSubject(1, $scope);
        $allowed   = $this->permissions->create('posts.edit', 'Edit Posts');
        $forbidden = $this->permissions->create('posts.publish', 'Publish Posts');
        $editor    = $this->roles->create('editor', 'Editor', scope: $scope);
        $publisher = $this->roles->create('publisher', 'Publisher', scope: $scope);
        $this->subjectPermissions->create($scope, SubjectPermissionSnapshot::from($allowed));
        $this->rolePermissions->create($editor, $allowed);
        $this->rolePermissions->create($publisher, $forbidden);

        try {
            $this->granter->grant($subject, $editor, $publisher);
            self::fail('Expected roles with a permission that exceeds the subject scope to be rejected.');
        } catch (InvalidAuthorization) {
            self::assertTrue($this->subjectRoles->allOf($subject)->isEmpty());
        }
    }

    public function test_grant_does_not_partially_assign_roles_when_a_direct_permission_exceeds_scope(): void
    {
        $scope      = new TestSubject(2);
        $subject    = new TestSubject(1, $scope);
        $role       = $this->roles->create('editor', 'Editor', scope: $scope);
        $permission = $this->permissions->create('posts.edit', 'Edit Posts');

        try {
            $this->granter->grant($subject, $role, $permission);
            self::fail('Expected an authorization that exceeds the subject scope to be rejected.');
        } catch (InvalidAuthorization) {
            self::assertTrue($this->subjectRoles->allOf($subject)->isEmpty());
            self::assertTrue($this->subjectPermissions->allOf($subject)->isEmpty());
        }
    }

    private function granter(ScopePropagationPolicy|null $propagation = null): Granter
    {
        return new Granter(
            $this->roles,
            $this->permissions,
            $this->subjectRoles,
            $this->subjectPermissions,
            $this->rolePermissions,
            $this->createScopeBoundary(
                $this->subjectPermissions,
                $this->subjectRoles,
                $propagation ?? new TransitiveScopePropagationPolicy(),
            ),
        );
    }

    private function createGranter(
        InMemoryRoleRepository           $roles,
        InMemorySubjectRoleRepository    $subjectRoles,
        InMemoryRolePermissionRepository $rolePermissions,
    ): Granter
    {
        $subjectPermissions = new InMemorySubjectPermissionRepository();

        return new Granter(
            $roles,
            new InMemoryPermissionRepository(),
            $subjectRoles,
            $subjectPermissions,
            $rolePermissions,
            $this->createScopeBoundary($subjectPermissions, $subjectRoles),
        );
    }
}
