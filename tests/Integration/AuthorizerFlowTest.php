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
use Vaened\Sentinel\Authorization\Junction;
use Vaened\Sentinel\Operators\Denier;
use Vaened\Sentinel\Operators\Granter;
use Vaened\Sentinel\Operators\Revoker;
use Vaened\Sentinel\Operators\SubjectPermissionSnapshot;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemoryPermissionRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemoryRolePermissionRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemoryRoleRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemorySubjectPermissionRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemorySubjectRoleRepository;
use Vaened\Sentinel\Tests\Runtime\TestPermission;
use Vaened\Sentinel\Tests\Runtime\TestRole;
use Vaened\Sentinel\Tests\Runtime\TestSubject;
use Vaened\Sentinel\Tests\TestCase;

final class AuthorizerFlowTest extends TestCase
{
    private Authorizer                          $authorizer;

    private Granter                             $granter;

    private Denier                              $denier;

    private Revoker                             $revoker;

    private InMemoryPermissionRepository        $permissions;

    private InMemoryRoleRepository              $roles;

    private InMemorySubjectPermissionRepository $subjectPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subjectPermissions = new InMemorySubjectPermissionRepository();
        $rolePermissions          = new InMemoryRolePermissionRepository();
        $subjectRoles             = new InMemorySubjectRoleRepository($rolePermissions);

        $this->permissions = new InMemoryPermissionRepository();
        $this->roles       = new InMemoryRoleRepository();
        $this->authorizer  = $this->createAuthorizer($this->subjectPermissions, $subjectRoles);

        $this->granter = new Granter(
            $this->roles,
            $this->permissions,
            $subjectRoles,
            $this->subjectPermissions,
            $rolePermissions,
            $this->authorizer,
        );
        $this->denier  = new Denier(
            $this->roles,
            $this->permissions,
            $this->subjectPermissions,
        );
        $this->revoker = new Revoker(
            $this->roles,
            $this->permissions,
            $subjectRoles,
            $this->subjectPermissions,
            $rolePermissions,
        );
    }

    public function test_direct_permission_lifecycle(): void
    {
        $subject    = new TestSubject(1);
        $permission = $this->permission('posts.edit');

        $this->granter->grant($subject, $permission);

        self::assertTrue($this->authorizer->can($subject, ['posts.edit']));

        $this->denier->deny($subject, $permission);

        self::assertFalse($this->authorizer->can($subject, ['posts.edit']));

        $this->revoker->revoke($subject, $permission);

        self::assertTrue($this->authorizer->cannot($subject, ['posts.edit']));
    }

    public function test_inherited_role_permission_lifecycle(): void
    {
        $subject    = new TestSubject(1);
        $role       = $this->role('editor');
        $permission = $this->permission('posts.edit');

        $this->granter->grant($role, $permission);
        $this->granter->grant($subject, $role);

        self::assertTrue($this->authorizer->can($subject, ['posts.edit']));

        $this->revoker->revoke($subject, $role);

        self::assertFalse($this->authorizer->can($subject, ['posts.edit']));
    }

    public function test_revoking_a_direct_permission_preserves_the_roles_permission(): void
    {
        $subject    = new TestSubject(1);
        $role       = $this->role('editor');
        $permission = $this->permission('posts.edit');

        $this->granter->grant($subject, $permission);
        $this->granter->grant($role, $permission);
        $this->granter->grant($subject, $role);

        $this->revoker->revoke($subject, $permission);

        self::assertTrue($this->authorizer->can($subject, ['posts.edit']));
    }

    public function test_revoking_a_denial_restores_the_inherited_permission(): void
    {
        $subject    = new TestSubject(1);
        $role       = $this->role('editor');
        $permission = $this->permission('posts.edit');

        $this->granter->grant($role, $permission);
        $this->granter->grant($subject, $role);
        $this->denier->deny($subject, $permission);

        self::assertFalse($this->authorizer->can($subject, ['posts.edit']));

        $this->revoker->revoke($subject, $permission);

        self::assertTrue($this->authorizer->can($subject, ['posts.edit']));
    }

    public function test_role_permission_lifecycle_after_the_role_is_assigned(): void
    {
        $subject    = new TestSubject(1);
        $role       = $this->role('editor');
        $permission = $this->permission('posts.edit');

        $this->granter->grant($subject, $role);
        $this->granter->grant($role, $permission);

        self::assertTrue($this->authorizer->can($subject, ['posts.edit']));

        $this->revoker->revoke($role, $permission);

        self::assertFalse($this->authorizer->can($subject, ['posts.edit']));
    }

    public function test_purging_a_subject_removes_direct_permissions_and_roles(): void
    {
        $subject          = new TestSubject(1);
        $role             = $this->role('editor');
        $directPermission = $this->permission('posts.edit');
        $rolePermission   = $this->permission('users.delete');

        $this->granter->grant($subject, $directPermission);
        $this->granter->grant($role, $rolePermission);
        $this->granter->grant($subject, $role);

        self::assertTrue($this->authorizer->can(
            $subject,
            ['posts.edit', 'users.delete'],
            Junction::And,
        ));
        self::assertTrue($this->authorizer->is($subject, ['editor']));

        $this->revoker->purge($subject);

        self::assertFalse($this->authorizer->can($subject, ['posts.edit', 'users.delete']));
        self::assertFalse($this->authorizer->is($subject, ['editor']));
    }

    public function test_transitive_scope_permission_lifecycle(): void
    {
        $permission = $this->permission('posts.edit');
        $root       = new TestSubject(3);
        $scope      = new TestSubject(2, $root);
        $subject    = new TestSubject(1, $scope);

        $this->seed($subject, $permission);

        self::assertFalse($this->authorizer->can($subject, ['posts.edit']));

        $this->seed($scope, $permission);

        self::assertFalse($this->authorizer->can($subject, ['posts.edit']));

        $this->seed($root, $permission);

        self::assertTrue($this->authorizer->can($subject, ['posts.edit']));

        $this->denier->deny($root, $permission);

        self::assertFalse($this->authorizer->can($subject, ['posts.edit']));
    }

    private function permission(string $code): TestPermission
    {
        return $this->permissions->create($code, ucfirst(str_replace('.', ' ', $code)));
    }

    private function role(string $code): TestRole
    {
        return $this->roles->create($code, ucfirst($code));
    }

    private function seed(TestSubject $subject, TestPermission ...$permissions): void
    {
        $this->subjectPermissions->create(
            $subject,
            ...array_map(SubjectPermissionSnapshot::from(...), $permissions),
        );
    }
}
