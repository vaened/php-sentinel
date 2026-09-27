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
use Vaened\Sentinel\Authorization\Authorizer;
use Vaened\Sentinel\Authorization\Junction;
use Vaened\Sentinel\Operators\Granter;
use Vaened\Sentinel\Operators\Revoker;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemoryPermissionRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemoryRolePermissionRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemoryRoleRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemorySubjectPermissionRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemorySubjectRoleRepository;
use Vaened\Sentinel\Tests\Runtime\TestRole;
use Vaened\Sentinel\Tests\Runtime\TestSubject;
use Vaened\Sentinel\Tests\TestCase;

final class AuthorizerRoleTest extends TestCase
{
    private Authorizer             $authorizer;

    private Granter                $granter;

    private Revoker                $revoker;

    private InMemoryRoleRepository $roles;

    private TestSubject            $subject;

    public static function roleEvaluationCases(): iterable
    {
        $cases = require dirname(__DIR__, 2) . '/Fixtures/authorizer-evaluation.php';

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
        $permissions        = new InMemoryPermissionRepository();

        $this->roles      = new InMemoryRoleRepository();
        $this->authorizer = $this->createAuthorizer($subjectPermissions, $subjectRoles);
        $this->granter    = new Granter(
            $this->roles,
            $permissions,
            $subjectRoles,
            $subjectPermissions,
            $rolePermissions,
            $this->authorizer,
        );
        $this->revoker    = new Revoker(
            $this->roles,
            $permissions,
            $subjectRoles,
            $subjectPermissions,
            $rolePermissions,
        );
        $this->subject    = new TestSubject(1);
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

    public function test_scopes_do_not_affect_role_evaluation(): void
    {
        $subject = new TestSubject(1, new TestSubject(2));
        $role    = $this->role('editor');

        $this->granter->grant($subject, $role);

        self::assertTrue($this->authorizer->is($subject, ['editor']));
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

    private function role(string $code): TestRole
    {
        return $this->roles->create($code, ucfirst($code));
    }
}
