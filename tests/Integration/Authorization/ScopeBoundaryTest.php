<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Tests\Integration\Authorization;

use Vaened\Sentinel\Authorization\PermissionEntryProvider;
use Vaened\Sentinel\Authorization\ScopeBoundary;
use Vaened\Sentinel\Errors\ScopeCycleDetected;
use Vaened\Sentinel\Operators\SubjectPermissionSnapshot;
use Vaened\Sentinel\Permission;
use Vaened\Sentinel\Propagation\DirectScopePropagationPolicy;
use Vaened\Sentinel\Propagation\NoPropagationPolicy;
use Vaened\Sentinel\Propagation\TransitiveScopePropagationPolicy;
use Vaened\Sentinel\Repositories\SubjectPermissionRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemoryPermissionRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemoryRolePermissionRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemorySubjectPermissionRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemorySubjectRoleRepository;
use Vaened\Sentinel\Tests\Runtime\TestRole;
use Vaened\Sentinel\Tests\Runtime\TestSubject;
use Vaened\Sentinel\Tests\TestCase;

final class ScopeBoundaryTest extends TestCase
{
    private InMemoryPermissionRepository        $permissions;

    private InMemorySubjectPermissionRepository $subjectPermissions;

    private InMemoryRolePermissionRepository    $rolePermissions;

    private InMemorySubjectRoleRepository       $subjectRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->permissions        = new InMemoryPermissionRepository();
        $this->subjectPermissions = new InMemorySubjectPermissionRepository();
        $this->rolePermissions    = new InMemoryRolePermissionRepository();
        $this->subjectRoles       = new InMemorySubjectRoleRepository($this->rolePermissions);
    }

    public function test_rejects_an_empty_permission_list(): void
    {
        self::assertFalse($this->boundary()->allows(new TestSubject(1), []));
    }

    public function test_allows_an_unscoped_owner(): void
    {
        $owner      = new TestSubject(1);
        $permission = $this->permission('posts.edit');

        self::assertTrue($this->boundary()->allows($owner, [$permission->code()]));
    }

    public function test_requires_every_requested_permission_in_the_immediate_scope(): void
    {
        $scope   = new TestSubject(2);
        $owner   = new TestSubject(1, $scope);
        $edit    = $this->permission('posts.edit');
        $publish = $this->permission('posts.publish');
        $this->allow($scope, $edit);

        self::assertFalse($this->boundary()->allows($owner, [$edit->code(), $publish->code()]));
    }

    public function test_allows_every_requested_permission_when_the_immediate_scope_allows_all_of_them(): void
    {
        $scope   = new TestSubject(2);
        $owner   = new TestSubject(1, $scope);
        $edit    = $this->permission('posts.edit');
        $publish = $this->permission('posts.publish');
        $this->allow($scope, $edit, $publish);

        self::assertTrue($this->boundary()->allows($owner, [$edit->code(), $publish->code()]));
    }

    public function test_direct_propagation_ignores_an_ancestor_scope(): void
    {
        $organization = new TestSubject(3);
        $team         = new TestSubject(2, $organization);
        $owner        = new TestSubject(1, $team);
        $permission   = $this->permission('posts.edit');
        $this->allow($team, $permission);

        self::assertTrue($this->boundary(new DirectScopePropagationPolicy())->allows($owner, [$permission->code()]));
    }

    public function test_no_propagation_ignores_all_scopes(): void
    {
        $scope      = new TestSubject(2);
        $owner      = new TestSubject(1, $scope);
        $permission = $this->permission('posts.edit');
        $this->subjectPermissions->create($scope, SubjectPermissionSnapshot::from($permission, true));

        self::assertTrue($this->boundary(new NoPropagationPolicy())->allows($owner, [$permission->code()]));
    }

    public function test_no_propagation_does_not_load_scope_permissions(): void
    {
        $permissions = $this->createMock(SubjectPermissionRepository::class);
        $roles       = $this->createMock(SubjectRoleRepository::class);
        $permissions->expects(self::never())
                    ->method('lookup');
        $roles->expects(self::never())
              ->method('grants');
        $boundary = new ScopeBoundary(
            new PermissionEntryProvider($permissions, $roles),
            new NoPropagationPolicy(),
        );

        self::assertTrue($boundary->allows(new TestSubject(1, new TestSubject(2)), ['posts.edit']));
    }

    public function test_transitive_propagation_requires_every_ancestor_scope_to_allow_every_permission(): void
    {
        $organization = new TestSubject(3);
        $team         = new TestSubject(2, $organization);
        $owner        = new TestSubject(1, $team);
        $permission   = $this->permission('posts.edit');
        $this->allow($team, $permission);

        self::assertFalse($this->boundary()->allows($owner, [$permission->code()]));
    }

    public function test_rejects_an_explicit_denial_from_an_ancestor_scope(): void
    {
        $organization = new TestSubject(3);
        $team         = new TestSubject(2, $organization);
        $owner        = new TestSubject(1, $team);
        $permission   = $this->permission('posts.edit');
        $this->allow($team, $permission);
        $this->subjectPermissions->create($organization, SubjectPermissionSnapshot::from($permission, true));

        self::assertFalse($this->boundary()->allows($owner, [$permission->code()]));
    }

    public function test_allows_a_permission_inherited_by_a_scope_from_its_role(): void
    {
        $scope      = new TestSubject(2);
        $owner      = new TestSubject(1, $scope);
        $role       = new TestRole(1, 'editor', 'Editor');
        $permission = $this->permission('posts.edit');
        $this->subjectRoles->create($scope, $role);
        $this->rolePermissions->create($role, $permission);

        self::assertTrue($this->boundary()->allows($owner, [$permission->code()]));
    }

    public function test_accepts_a_role_owner_against_its_scope(): void
    {
        $scope      = new TestSubject(2);
        $owner      = new TestRole(1, 'editor', 'Editor', scope: $scope);
        $permission = $this->permission('posts.edit');
        $this->allow($scope, $permission);

        self::assertTrue($this->boundary()->allows($owner, [$permission->code()]));
    }

    public function test_propagates_scope_cycles(): void
    {
        $first      = new TestSubject(1);
        $second     = new TestSubject(2, $first);
        $owner      = new TestSubject(3, $second);
        $permission = $this->permission('posts.edit');
        $first->setScope($second);
        $this->allow($first, $permission);
        $this->allow($second, $permission);

        $this->expectException(ScopeCycleDetected::class);

        $this->boundary()->allows($owner, [$permission->code()]);
    }

    private function boundary(
        DirectScopePropagationPolicy|NoPropagationPolicy|TransitiveScopePropagationPolicy|null $propagation = null,
    ): ScopeBoundary
    {
        return $this->createScopeBoundary(
            $this->subjectPermissions,
            $this->subjectRoles,
            $propagation ?? new TransitiveScopePropagationPolicy(),
        );
    }

    private function permission(string $code): Permission
    {
        return $this->permissions->create($code, $code);
    }

    private function allow(TestSubject $subject, Permission ...$permissions): void
    {
        $this->subjectPermissions->create(
            $subject,
            ...array_map(SubjectPermissionSnapshot::from(...), $permissions),
        );
    }
}
