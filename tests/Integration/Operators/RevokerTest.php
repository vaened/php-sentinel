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

use Vaened\Sentinel\Operators\Granter;
use Vaened\Sentinel\Operators\Revoker;
use Vaened\Sentinel\Operators\SubjectPermissionSnapshot;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemoryPermissionRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemoryRolePermissionRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemoryRoleRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemorySubjectPermissionRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemorySubjectRoleRepository;
use Vaened\Sentinel\Tests\Runtime\TestSubject;
use Vaened\Sentinel\Tests\TestCase;

final class RevokerTest extends TestCase
{
    private InMemoryPermissionRepository        $permissions;

    private InMemoryRoleRepository              $roles;

    private InMemoryRolePermissionRepository    $rolePermissions;

    private InMemorySubjectPermissionRepository $subjectPermissions;

    private InMemorySubjectRoleRepository       $subjectRoles;

    private Granter                             $granter;

    private Revoker                             $revoker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->permissions        = new InMemoryPermissionRepository();
        $this->roles              = new InMemoryRoleRepository();
        $this->rolePermissions    = new InMemoryRolePermissionRepository();
        $this->subjectPermissions = new InMemorySubjectPermissionRepository();
        $this->subjectRoles       = new InMemorySubjectRoleRepository($this->rolePermissions);
        $this->granter            = new Granter(
            $this->roles,
            $this->permissions,
            $this->subjectRoles,
            $this->subjectPermissions,
            $this->rolePermissions,
        );
        $this->revoker            = new Revoker(
            $this->roles,
            $this->permissions,
            $this->subjectRoles,
            $this->subjectPermissions,
            $this->rolePermissions,
        );
    }

    public function test_revoke_removes_a_direct_subject_permission(): void
    {
        $subject    = new TestSubject(1);
        $permission = $this->permissions->create('posts.edit', 'Edit Posts');

        $this->granter->grant($subject, $permission);
        $this->revoker->revoke($subject, $permission);

        self::assertTrue($this->subjectPermissions->allOf($subject)->isEmpty());
    }

    public function test_revoke_removes_a_direct_denial(): void
    {
        $subject    = new TestSubject(1);
        $permission = $this->permissions->create('posts.edit', 'Edit Posts');
        $this->subjectPermissions->create(
            $subject,
            new SubjectPermissionSnapshot($permission->id(), $permission->code(), true),
        );

        $this->revoker->revoke($subject, $permission);

        self::assertTrue($this->subjectPermissions->allOf($subject)->isEmpty());
    }

    public function test_revoke_removes_an_assigned_role(): void
    {
        $subject = new TestSubject(1);
        $role    = $this->roles->create('editor', 'Editor');

        $this->granter->grant($subject, $role);
        $this->revoker->revoke($subject, $role);

        self::assertTrue($this->subjectRoles->allOf($subject)->isEmpty());
    }

    public function test_revoke_removes_an_assigned_role_permission(): void
    {
        $role       = $this->roles->create('editor', 'Editor');
        $permission = $this->permissions->create('posts.edit', 'Edit Posts');

        $this->granter->grant($role, $permission);
        $this->revoker->revoke($role, $permission);

        self::assertTrue($this->rolePermissions->allOf($role)->isEmpty());
    }

    public function test_revoke_removes_a_scoped_role_from_a_subject_in_the_same_scope(): void
    {
        $scope   = new TestSubject(2);
        $subject = new TestSubject(1, $scope);
        $role    = $this->roles->create('editor', 'Editor', scope: $scope);

        $this->granter->grant($subject, $role);
        $this->revoker->revoke($subject, $role);

        self::assertTrue($this->subjectRoles->allOf($subject)->isEmpty());
    }

    public function test_revoke_removes_a_legacy_role_assignment_with_an_incompatible_scope(): void
    {
        $subject = new TestSubject(1, new TestSubject(2));
        $role    = $this->roles->create('editor', 'Editor', scope: new TestSubject(3));
        $this->subjectRoles->create($subject, $role);

        $this->revoker->revoke($subject, $role);

        self::assertTrue($this->subjectRoles->allOf($subject)->isEmpty());
    }

    public function test_purge_removes_direct_permissions_and_roles(): void
    {
        $subject          = new TestSubject(1);
        $directPermission = $this->permissions->create('posts.edit', 'Edit Posts');
        $role             = $this->roles->create('editor', 'Editor');

        $this->granter->grant($subject, $directPermission);
        $this->granter->grant($subject, $role);
        $this->revoker->purge($subject);

        self::assertTrue($this->subjectPermissions->allOf($subject)->isEmpty());
        self::assertTrue($this->subjectRoles->allOf($subject)->isEmpty());
    }
}
