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

use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Vaened\Sentinel\Authorization;
use Vaened\Sentinel\Authorization\Authorizer;
use Vaened\Sentinel\Authorization\Junction;
use Vaened\Sentinel\Authorization\PermissionEntryProvider;
use Vaened\Sentinel\Authorization\RoleEntryProvider;
use Vaened\Sentinel\Errors\ScopeCycleDetected;
use Vaened\Sentinel\Operators\Denier;
use Vaened\Sentinel\Operators\Granter;
use Vaened\Sentinel\Operators\Revoker;
use Vaened\Sentinel\Operators\SubjectPermissionSnapshot;
use Vaened\Sentinel\Permission;
use Vaened\Sentinel\Propagation\DirectScopePropagationPolicy;
use Vaened\Sentinel\Propagation\ScopePropagationPolicy;
use Vaened\Sentinel\Propagation\TransitiveScopePropagationPolicy;
use Vaened\Sentinel\Repositories\SubjectPermissionRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;
use Vaened\Sentinel\Role;
use Vaened\Sentinel\Scopeable;
use Vaened\Sentinel\Subject;
use Vaened\Sentinel\SubjectPermissions;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemoryPermissionRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemoryRolePermissionRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemoryRoleRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemorySubjectPermissionRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemorySubjectRoleRepository;
use Vaened\Sentinel\Tests\Runtime\TestPermission;
use Vaened\Sentinel\Tests\Runtime\TestRole;
use Vaened\Sentinel\Tests\Runtime\TestSubject;
use Vaened\Sentinel\Tests\Runtime\TestSubjectPermission;
use Vaened\Sentinel\Tests\TestCase;

final class AuthorizerPermissionTest extends TestCase
{
    private Authorizer                          $authorizer;

    private Granter                             $granter;

    private Denier                              $denier;

    private Revoker                             $revoker;

    private InMemoryRoleRepository              $roles;

    private InMemoryPermissionRepository        $permissions;

    private TestSubject                         $subject;

    private TestRole                            $role;

    private InMemorySubjectPermissionRepository $subjectPermissions;

    private InMemorySubjectRoleRepository       $subjectRoles;

    private InMemoryRolePermissionRepository    $rolePermissions;

    public static function permissionEvaluationCases(): iterable
    {
        $cases = require dirname(__DIR__, 2) . '/Fixtures/authorizer-evaluation.php';

        foreach ($cases['permission_evaluation'] as $name => $case) {
            yield $name => [
                $case['method'],
                $case['junction'],
                $case['codes'],
                $case['subject_allowed'],
                $case['subject_denied'],
                $case['role_permissions'],
                $case['assign_role'],
                $case['expected'],
            ];
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->subjectPermissions = new InMemorySubjectPermissionRepository();
        $this->rolePermissions    = new InMemoryRolePermissionRepository();
        $this->subjectRoles       = new InMemorySubjectRoleRepository($this->rolePermissions);

        $this->roles       = new InMemoryRoleRepository();
        $this->permissions = new InMemoryPermissionRepository();
        $this->authorizer  = $this->createAuthorizer($this->subjectPermissions, $this->subjectRoles);

        $this->granter = new Granter(
            $this->roles,
            $this->permissions,
            $this->subjectRoles,
            $this->subjectPermissions,
            $this->rolePermissions,
            $this->authorizer,
        );

        $this->denier = new Denier(
            $this->roles,
            $this->permissions,
            $this->subjectPermissions,
        );

        $this->revoker = new Revoker(
            $this->roles,
            $this->permissions,
            $this->subjectRoles,
            $this->subjectPermissions,
            $this->rolePermissions,
        );

        $this->subject = new TestSubject(1);
        $this->role    = $this->role('admin');
    }

    public function test_can_returns_false_for_empty_permission_list(): void
    {
        self::assertFalse($this->authorizer->can($this->subject, []));
    }

    public function test_cannot_returns_true_for_empty_permission_list(): void
    {
        self::assertTrue($this->authorizer->cannot($this->subject, []));
    }

    public function test_subject_can_use_a_direct_permission(): void
    {
        $permission = $this->permission('posts.edit');

        $this->granter->grant($this->subject, $permission);

        self::assertTrue($this->authorizer->can($this->subject, ['posts.edit']));
        self::assertFalse($this->authorizer->cannot($this->subject, ['posts.edit']));
    }

    public function test_subject_inherits_a_permission_from_role(): void
    {
        $permission = $this->permission('posts.edit');

        $this->granter->grant($this->role, $permission);
        $this->granter->grant($this->subject, $this->role);

        self::assertTrue($this->authorizer->can($this->subject, ['posts.edit']));
    }

    public function test_subject_does_not_keep_a_role_permission_after_the_role_is_revoked(): void
    {
        $permission = $this->permission('posts.edit');

        $this->granter->grant($this->role, $permission);
        $this->granter->grant($this->subject, $this->role);
        $this->granter->grant($this->subject, $permission);

        $this->revoker->revoke($this->subject, $this->role);

        self::assertFalse($this->authorizer->can($this->subject, ['posts.edit']));
    }

    public function test_subject_denial_prevails_over_role_permission(): void
    {
        $permission = $this->permission('users.delete');

        $this->granter->grant($this->role, $permission);
        $this->granter->grant($this->subject, $this->role);
        $this->denier->deny($this->subject, $permission);

        self::assertFalse($this->authorizer->can($this->subject, ['users.delete']));
        self::assertTrue($this->authorizer->cannot($this->subject, ['users.delete']));
    }

    public function test_subject_cannot_when_permission_is_missing(): void
    {
        self::assertFalse($this->authorizer->can($this->subject, ['posts.edit']));
        self::assertTrue($this->authorizer->cannot($this->subject, ['posts.edit']));
    }

    public function test_subject_cannot_after_permission_is_revoked(): void
    {
        $permission = $this->permission('posts.edit');

        $this->granter->grant($this->subject, $permission);

        $this->revoker->revoke($this->subject, $permission);

        self::assertFalse($this->authorizer->can($this->subject, ['posts.edit']));
        self::assertTrue($this->authorizer->cannot($this->subject, ['posts.edit']));
    }

    public function test_subject_requires_a_permission_on_itself_and_its_scope(): void
    {
        $permission = $this->permission('posts.edit');
        $scope      = new TestSubject(2);
        $subject    = new TestSubject(1, $scope);

        $this->seed($subject, $permission);

        self::assertFalse($this->authorizer->can($subject, ['posts.edit']));

        $this->seed($scope, $permission);

        self::assertTrue($this->authorizer->can($subject, ['posts.edit']));
    }

    public function test_subject_can_combine_its_direct_permission_with_its_scopes_role_permission(): void
    {
        $permission = $this->permission('posts.edit');
        $scope      = new TestSubject(2);
        $subject    = new TestSubject(1, $scope);
        $scopeRole  = $this->role('scope-admin');

        $this->seed($subject, $permission);
        $this->seed($scopeRole, $permission);
        $this->seed($scope, $scopeRole);

        self::assertTrue($this->authorizer->can($subject, ['posts.edit']));
    }

    public function test_subject_can_combine_its_role_permission_with_its_scopes_direct_permission(): void
    {
        $permission = $this->permission('posts.edit');
        $scope      = new TestSubject(2);
        $subject    = new TestSubject(1, $scope);

        $this->seed($this->role, $permission);
        $this->seed($subject, $this->role);
        $this->seed($scope, $permission);

        self::assertTrue($this->authorizer->can($subject, ['posts.edit']));
    }

    public function test_scope_denial_overrides_a_subject_permission(): void
    {
        $permission = $this->permission('posts.edit');
        $scope      = new TestSubject(2);
        $subject    = new TestSubject(1, $scope);

        $this->seed($subject, $permission);
        $this->denier->deny($scope, $permission);

        self::assertFalse($this->authorizer->can($subject, ['posts.edit']));
        self::assertTrue($this->authorizer->cannot($subject, ['posts.edit']));
    }

    public function test_subject_cannot_use_a_permission_missing_from_its_scope(): void
    {
        $permission = $this->permission('posts.edit');
        $subject    = new TestSubject(1, new TestSubject(2));

        $this->seed($subject, $permission);

        self::assertFalse($this->authorizer->can($subject, ['posts.edit']));
    }

    public function test_or_does_not_combine_different_permissions_from_different_scope_levels(): void
    {
        $posts   = $this->permission('posts.edit');
        $users   = $this->permission('users.delete');
        $scope   = new TestSubject(2);
        $subject = new TestSubject(1, $scope);

        $this->seed($subject, $posts);
        $this->seed($scope, $users);

        self::assertFalse($this->authorizer->can($subject, ['posts.edit', 'users.delete'], Junction::Or));
    }

    public function test_or_allows_a_single_permission_available_at_every_scope_level(): void
    {
        $posts   = $this->permission('posts.edit');
        $users   = $this->permission('users.delete');
        $scope   = new TestSubject(2);
        $subject = new TestSubject(1, $scope);

        $this->seed($subject, $posts, $users);
        $this->seed($scope, $posts);

        self::assertTrue($this->authorizer->can($subject, ['posts.edit', 'users.delete'], Junction::Or));
    }

    public function test_and_requires_every_permission_at_every_scope_level(): void
    {
        $posts   = $this->permission('posts.edit');
        $users   = $this->permission('users.delete');
        $scope   = new TestSubject(2);
        $subject = new TestSubject(1, $scope);

        $this->seed($subject, $posts, $users);
        $this->seed($scope, $posts);

        self::assertFalse($this->authorizer->can($subject, ['posts.edit', 'users.delete'], Junction::And));

        $this->seed($scope, $users);

        self::assertTrue($this->authorizer->can($subject, ['posts.edit', 'users.delete'], Junction::And));
    }

    public function test_subject_requires_a_permission_through_every_scope_ancestor(): void
    {
        $permission = $this->permission('posts.edit');
        $root       = new TestSubject(3);
        $scope      = new TestSubject(2, $root);
        $subject    = new TestSubject(1, $scope);

        $this->seed($subject, $permission);
        $this->seed($scope, $permission);

        self::assertFalse($this->authorizer->can($subject, ['posts.edit']));

        $this->seed($root, $permission);

        self::assertTrue($this->authorizer->can($subject, ['posts.edit']));
    }

    public function test_direct_scope_propagation_ignores_scope_ancestors(): void
    {
        $permission = $this->permission('posts.edit');
        $root       = new TestSubject(3);
        $scope      = new TestSubject(2, $root);
        $subject    = new TestSubject(1, $scope);
        $authorizer = new Authorizer(
            new PermissionEntryProvider($this->subjectPermissions, $this->subjectRoles),
            new RoleEntryProvider($this->subjectRoles),
            new DirectScopePropagationPolicy(),
        );

        $this->seed($subject, $permission);
        $this->seed($scope, $permission);

        self::assertTrue($authorizer->can($subject, ['posts.edit']));
    }

    public function test_direct_scope_propagation_requires_every_permission_for_and(): void
    {
        $posts      = $this->permission('posts.edit');
        $users      = $this->permission('users.delete');
        $root       = new TestSubject(3);
        $scope      = new TestSubject(2, $root);
        $subject    = new TestSubject(1, $scope);
        $authorizer = $this->scopedAuthorizer(new DirectScopePropagationPolicy());

        $this->seed($subject, $posts, $users);
        $this->seed($scope, $posts);
        $this->seed($root, $posts, $users);

        self::assertFalse($authorizer->can($subject, ['posts.edit', 'users.delete'], Junction::And));

        $this->seed($scope, $users);

        self::assertTrue($authorizer->can($subject, ['posts.edit', 'users.delete'], Junction::And));
    }

    public function test_direct_scope_propagation_requires_one_matching_permission_for_or(): void
    {
        $first      = $this->permission('first');
        $second     = $this->permission('second');
        $third      = $this->permission('third');
        $root       = new TestSubject(3);
        $scope      = new TestSubject(2, $root);
        $subject    = new TestSubject(1, $scope);
        $authorizer = $this->scopedAuthorizer(new DirectScopePropagationPolicy());

        $this->seed($subject, $first);
        $this->seed($scope, $second);
        $this->seed($root, $third);

        self::assertFalse($authorizer->can($subject, ['first', 'second', 'third'], Junction::Or));

        $this->seed($scope, $first);

        self::assertTrue($authorizer->can($subject, ['first', 'second', 'third'], Junction::Or));
    }

    public function test_transitive_scope_propagation_or_does_not_mix_permissions_across_levels(): void
    {
        $first      = $this->permission('first');
        $second     = $this->permission('second');
        $third      = $this->permission('third');
        $root       = new TestSubject(3);
        $scope      = new TestSubject(2, $root);
        $subject    = new TestSubject(1, $scope);
        $authorizer = $this->scopedAuthorizer(new TransitiveScopePropagationPolicy());

        $this->seed($subject, $third);
        $this->seed($scope, $first);
        $this->seed($root, $second);

        self::assertFalse($authorizer->can($subject, ['first', 'second', 'third'], Junction::Or));
    }

    public function test_transitive_scope_propagation_allows_or_when_one_permission_passes_every_level(): void
    {
        $first      = $this->permission('first');
        $second     = $this->permission('second');
        $third      = $this->permission('third');
        $root       = new TestSubject(3);
        $scope      = new TestSubject(2, $root);
        $subject    = new TestSubject(1, $scope);
        $authorizer = $this->scopedAuthorizer(new TransitiveScopePropagationPolicy());

        $this->seed($subject, $first, $second, $third);
        $this->seed($scope, $first, $third);
        $this->seed($root, $third);

        self::assertTrue($authorizer->can($subject, ['first', 'second', 'third'], Junction::Or));
    }

    public function test_transitive_scope_propagation_requires_every_permission_for_and(): void
    {
        $posts      = $this->permission('posts.edit');
        $users      = $this->permission('users.delete');
        $root       = new TestSubject(3);
        $scope      = new TestSubject(2, $root);
        $subject    = new TestSubject(1, $scope);
        $authorizer = $this->scopedAuthorizer(new TransitiveScopePropagationPolicy());

        $this->seed($subject, $posts, $users);
        $this->seed($scope, $posts, $users);
        $this->seed($root, $posts);

        self::assertFalse($authorizer->can($subject, ['posts.edit', 'users.delete'], Junction::And));

        $this->seed($root, $users);

        self::assertTrue($authorizer->can($subject, ['posts.edit', 'users.delete'], Junction::And));
    }

    public function test_transitive_scope_propagation_resolves_role_permissions_at_every_level(): void
    {
        $permission  = $this->permission('posts.edit');
        $root        = new TestSubject(3);
        $scope       = new TestSubject(2, $root);
        $subject     = new TestSubject(1, $scope);
        $subjectRole = $this->role('subject-role');
        $scopeRole   = $this->role('scope-role');
        $rootRole    = $this->role('root-role');
        $authorizer  = $this->scopedAuthorizer(new TransitiveScopePropagationPolicy());

        $this->seed($subjectRole, $permission);
        $this->seed($scopeRole, $permission);
        $this->seed($rootRole, $permission);
        $this->seed($subject, $subjectRole);
        $this->seed($scope, $scopeRole);
        $this->seed($root, $rootRole);

        self::assertTrue($authorizer->can($subject, ['posts.edit']));
    }

    public function test_transitive_scope_propagation_rejects_a_role_permission_denied_by_an_ancestor(): void
    {
        $permission  = $this->permission('posts.edit');
        $root        = new TestSubject(3);
        $scope       = new TestSubject(2, $root);
        $subject     = new TestSubject(1, $scope);
        $subjectRole = $this->role('subject-role');
        $scopeRole   = $this->role('scope-role');
        $rootRole    = $this->role('root-role');
        $authorizer  = $this->scopedAuthorizer(new TransitiveScopePropagationPolicy());

        $this->seed($subjectRole, $permission);
        $this->seed($scopeRole, $permission);
        $this->seed($rootRole, $permission);
        $this->seed($subject, $subjectRole);
        $this->seed($scope, $scopeRole);
        $this->seed($root, $rootRole);
        $this->denier->deny($root, $permission);

        self::assertFalse($authorizer->can($subject, ['posts.edit']));
    }

    public function test_scope_propagation_cannot_omit_a_subjects_own_permissions(): void
    {
        $permission = $this->permission('posts.edit');
        $policy     = new class implements ScopePropagationPolicy {
            public function scopes(Scopeable $owner): iterable
            {
                return [];
            }
        };
        $authorizer = new Authorizer(
            new PermissionEntryProvider($this->subjectPermissions, $this->subjectRoles),
            new RoleEntryProvider($this->subjectRoles),
            $policy,
        );

        self::assertFalse($authorizer->can($this->subject, ['posts.edit']));

        $this->granter->grant($this->subject, $permission);

        self::assertTrue($authorizer->can($this->subject, ['posts.edit']));
    }

    public function test_can_throws_for_a_self_referential_scope(): void
    {
        $subject = new TestSubject(1);
        $subject->setScope($subject);

        $this->expectException(ScopeCycleDetected::class);
        $this->expectExceptionMessage('Authorization scope cycle detected for subject [Vaened\\Sentinel\\Tests\\Runtime\\TestSubject:1].');

        $this->authorizer->can($subject, ['posts.edit']);
    }

    public function test_can_throws_for_a_two_node_scope_cycle(): void
    {
        $first  = new TestSubject(1);
        $second = new TestSubject(2, $first);
        $first->setScope($second);

        $this->expectException(ScopeCycleDetected::class);
        $this->expectExceptionMessage('Authorization scope cycle detected for subject [Vaened\\Sentinel\\Tests\\Runtime\\TestSubject:1].');

        $this->authorizer->can($first, ['posts.edit']);
    }

    public function test_can_propagates_a_scope_resolution_exception(): void
    {
        $subject = $this->createMock(Subject::class);
        $failure = new RuntimeException('Unable to resolve scope.');

        $subject->method('id')
                ->willReturn(1);
        $subject->method('scope')
                ->willThrowException($failure);

        $this->expectExceptionObject($failure);

        $this->authorizer->can($subject, ['posts.edit']);
    }

    public function test_can_resolves_the_full_permission_list_once_per_scope(): void
    {
        $child       = new TestSubject(1, new TestSubject(2, new TestSubject(3)));
        $permission  = new TestPermission(1, 'posts.edit', 'Edit Posts');
        $permissions = $this->createMock(SubjectPermissionRepository::class);
        $roles       = $this->createMock(SubjectRoleRepository::class);

        $permissions->expects(self::exactly(3))
                    ->method('lookup')
                    ->with(self::isInstanceOf(TestSubject::class), 'posts.edit', 'users.delete')
                    ->willReturn(new SubjectPermissions([
                        TestSubjectPermission::from($permission),
                        TestSubjectPermission::from(new TestPermission(2, 'users.delete', 'Delete Users')),
                    ]));
        $roles->expects(self::never())
              ->method('grants');

        $authorizer = new Authorizer(
            new PermissionEntryProvider($permissions, $roles),
            new RoleEntryProvider($roles),
        );

        self::assertTrue($authorizer->can($child, ['posts.edit', 'users.delete'], Junction::And));
    }

    #[DataProvider('permissionEvaluationCases')]
    public function test_permission_evaluation(
        string   $method,
        Junction $junction,
        array    $codes,
        array    $subjectAllowed,
        array    $subjectDenied,
        array    $rolePermissions,
        bool     $assignRole,
        bool     $expected,
    ): void
    {
        $permissions = [];

        foreach ($subjectAllowed as $code) {
            $permissions[$code] ??= $this->permission($code);
            $this->granter->grant($this->subject, $permissions[$code]);
        }

        foreach ($subjectDenied as $code) {
            $permissions[$code] ??= $this->permission($code);
            $this->denier->deny($this->subject, $permissions[$code]);
        }

        foreach ($rolePermissions as $code) {
            $permissions[$code] ??= $this->permission($code);
            $this->granter->grant($this->role, $permissions[$code]);
        }

        if ($assignRole) {
            $this->granter->grant($this->subject, $this->role);
        }

        self::assertSame($expected, $this->authorizer->{$method}($this->subject, $codes, $junction));
    }

    protected function scopedAuthorizer(ScopePropagationPolicy $propagation): Authorizer
    {
        return new Authorizer(
            new PermissionEntryProvider($this->subjectPermissions, $this->subjectRoles),
            new RoleEntryProvider($this->subjectRoles),
            $propagation,
        );
    }

    protected function permission(string $code): TestPermission
    {
        $permission = $this->permissions->lookup($code)->find($code);

        if ($permission instanceof TestPermission) {
            return $permission;
        }

        return $this->permissions->create($code, ucfirst(str_replace('.', ' ', $code)));
    }

    protected function role(string $code): TestRole
    {
        $role = $this->roles->lookup(null, $code)->find($code);

        if ($role instanceof TestRole) {
            return $role;
        }

        return $this->roles->create($code, ucfirst($code));
    }

    private function seed(Subject|Role $owner, Authorization ...$authorizations): void
    {
        $permissions = [];
        $roles       = [];

        foreach ($authorizations as $authorization) {
            if ($authorization instanceof Permission) {
                $permissions[] = $authorization;
                continue;
            }

            if ($authorization instanceof Role) {
                $roles[] = $authorization;
            }
        }

        if ($owner instanceof Role) {
            $this->rolePermissions->create($owner, ...$permissions);

            return;
        }

        if (!empty($permissions)) {
            $this->subjectPermissions->create(
                $owner,
                ...array_map(SubjectPermissionSnapshot::from(...), $permissions),
            );
        }

        if (!empty($roles)) {
            $this->subjectRoles->create($owner, ...$roles);
        }
    }
}
