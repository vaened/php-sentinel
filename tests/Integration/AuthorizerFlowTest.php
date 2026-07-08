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

use PHPUnit\Framework\Attributes\DataProvider;
use Vaened\Sentinel\Authorization\Authorizer;
use Vaened\Sentinel\Authorization\Junction;
use Vaened\Sentinel\Authorization\PermissionEntryProvider;
use Vaened\Sentinel\Authorization\RoleEntryProvider;
use Vaened\Sentinel\Errors\PermissionNotFound;
use Vaened\Sentinel\Errors\RoleNotFound;
use Vaened\Sentinel\Operators\Denier;
use Vaened\Sentinel\Operators\Granter;
use Vaened\Sentinel\Operators\Revoker;
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
    private Authorizer                   $authorizer;

    private Granter                      $granter;

    private Denier                       $denier;

    private Revoker                      $revoker;

    private InMemoryRoleRepository       $roles;

    private InMemoryPermissionRepository $permissions;

    private TestSubject                  $subject;

    private TestRole                     $role;

    public static function permissionEvaluationCases(): iterable
    {
        $cases = require dirname(__DIR__) . '/Fixtures/authorizer-flow.php';

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

    public static function roleEvaluationCases(): iterable
    {
        $cases = require dirname(__DIR__) . '/Fixtures/authorizer-flow.php';

        foreach ($cases['role_evaluation'] as $name => $case) {
            yield $name => [
                $case['method'],
                $case['junction'],
                $case['assigned_roles'],
                $case['codes'],
                $case['expected'],
            ];
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $subjectPermissions = new InMemorySubjectPermissionRepository();
        $rolePermissions    = new InMemoryRolePermissionRepository();
        $subjectRoles       = new InMemorySubjectRoleRepository($rolePermissions);

        $this->roles       = new InMemoryRoleRepository();
        $this->permissions = new InMemoryPermissionRepository();

        $this->granter = new Granter(
            $this->roles,
            $this->permissions,
            $subjectRoles,
            $subjectPermissions,
            $rolePermissions,
        );

        $this->denier = new Denier(
            $this->roles,
            $this->permissions,
            $subjectPermissions,
        );

        $this->revoker = new Revoker(
            $this->roles,
            $this->permissions,
            $subjectRoles,
            $subjectPermissions,
            $rolePermissions,
        );

        $this->authorizer = new Authorizer(
            new PermissionEntryProvider($subjectPermissions, $subjectRoles),
            new RoleEntryProvider($subjectRoles),
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

    public function test_grant_throws_when_permission_does_not_exist_in_the_catalog(): void
    {
        $phantom = new TestPermission(999, 'phantom.perm', 'Phantom');

        $this->expectException(PermissionNotFound::class);
        $this->granter->grant($this->subject, $phantom);
    }

    public function test_grant_throws_when_role_does_not_exist_in_the_catalog(): void
    {
        $phantom = new TestRole(999, 'phantom.role', 'Phantom');

        $this->expectException(RoleNotFound::class);
        $this->granter->grant($this->subject, $phantom);
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

    public function test_is_returns_false_for_empty_role_list(): void
    {
        self::assertFalse($this->authorizer->is($this->subject, []));
    }

    public function test_isnt_returns_true_for_empty_role_list(): void
    {
        self::assertTrue($this->authorizer->isnt($this->subject, []));
    }

    public function test_subject_isnt_after_role_is_revoked(): void
    {
        $role = $this->role('editor');

        $this->granter->grant($this->subject, $role);

        $this->revoker->revoke($this->subject, $role);

        self::assertFalse($this->authorizer->is($this->subject, ['editor']));
        self::assertTrue($this->authorizer->isnt($this->subject, ['editor']));
    }

    #[DataProvider('roleEvaluationCases')]
    public function test_role_evaluation(
        string   $method,
        Junction $junction,
        array    $assignedRoles,
        array    $codes,
        bool     $expected,
    ): void
    {
        foreach ($assignedRoles as $code) {
            $this->granter->grant($this->subject, $this->role($code));
        }

        self::assertSame($expected, $this->authorizer->{$method}($this->subject, $codes, $junction));
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
        $role = $this->roles->lookup($code)->find($code);

        if ($role instanceof TestRole) {
            return $role;
        }

        return $this->roles->create($code, ucfirst($code));
    }
}
