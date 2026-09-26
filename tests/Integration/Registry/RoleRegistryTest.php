<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Tests\Integration\Registry;

use Vaened\Sentinel\Errors\RoleAlreadyExists;
use Vaened\Sentinel\Errors\RoleInUse;
use Vaened\Sentinel\Errors\RoleNotFound;
use Vaened\Sentinel\Identifier;
use Vaened\Sentinel\Registry\RoleRegistry;
use Vaened\Sentinel\Repositories\RoleRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;
use Vaened\Sentinel\Roles;
use Vaened\Sentinel\Subject;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemoryRolePermissionRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemoryRoleRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemorySubjectRoleRepository;
use Vaened\Sentinel\Tests\Runtime\TestRole;
use Vaened\Sentinel\Tests\Runtime\TestSubject;
use Vaened\Sentinel\Tests\TestCase;

final class RoleRegistryTest extends TestCase
{
    private InMemoryRoleRepository        $roles;

    private InMemorySubjectRoleRepository $subjectRoles;

    private RoleRegistry                  $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roles        = new InMemoryRoleRepository();
        $this->subjectRoles = new InMemorySubjectRoleRepository(new InMemoryRolePermissionRepository());

        $this->registry = new RoleRegistry($this->roles, $this->subjectRoles);
    }

    public function test_create_returns_a_persisted_role_with_provided_attributes(): void
    {
        $role = $this->registry->create('admin', 'Administrator', 'Full access');

        self::assertSame('admin', $role->code());
        self::assertSame('Administrator', $role->name());
        self::assertSame('Full access', $role->description());
        self::assertTrue($this->roles->exists($role->id()));
    }

    public function test_create_throws_when_code_already_exists(): void
    {
        $this->registry->create('admin', 'Administrator');

        try {
            $this->registry->create('admin', 'Other Name');
            self::fail('Expected duplicate global role creation to fail.');
        } catch (RoleAlreadyExists $exception) {
            self::assertSame(RoleAlreadyExists::fromCode('admin')->getMessage(), $exception->getMessage());
        }
    }

    public function test_update_renames_existing_role(): void
    {
        $role = $this->registry->create('admin', 'Administrator');

        $this->registry->update($role->id(), 'Owner', 'Full access to everything');

        $reloaded = $this->roles->lookup(null, 'admin')->find('admin');
        self::assertInstanceOf(TestRole::class, $reloaded);
        self::assertSame('Owner', $reloaded->name());
        self::assertSame('Full access to everything', $reloaded->description());
    }

    public function test_update_can_clear_description(): void
    {
        $role = $this->registry->create('admin', 'Administrator', 'Full access');

        $this->registry->update($role->id(), 'Administrator', null);

        $reloaded = $this->roles->lookup(null, 'admin')->find('admin');
        self::assertInstanceOf(TestRole::class, $reloaded);
        self::assertNull($reloaded->description());
    }

    public function test_update_retains_a_roles_scope(): void
    {
        $scope = new TestSubject(1);
        $role  = $this->registry->create('admin', 'Administrator', scope: $scope);

        $this->registry->update($role->id(), 'Owner');

        $reloaded = $this->roles->lookup($scope, 'admin')->find('admin');
        self::assertSame($scope, $reloaded?->scope());
    }

    public function test_update_throws_when_id_is_missing(): void
    {
        $this->expectException(RoleNotFound::class);
        $this->registry->update(999, 'Other Name');
    }

    public function test_remove_silently_ignores_unknown_id(): void
    {
        $this->registry->remove(999);

        $this->expectNotToPerformAssertions();
    }

    public function test_remove_throws_when_a_subject_has_the_role(): void
    {
        $subject = new TestSubject(1);

        $role = $this->registry->create('admin', 'Administrator');
        $this->subjectRoles->create($subject, $role);

        $this->expectException(RoleInUse::class);
        $this->registry->remove($role->id());
    }

    public function test_remove_succeeds_when_no_one_has_it(): void
    {
        $role = $this->registry->create('admin', 'Administrator');

        $this->registry->remove($role->id());

        self::assertFalse($this->roles->exists($role->id()));
    }

    public function test_lookup_delegates_to_the_role_repository(): void
    {
        $this->registry->create('admin', 'Administrator');
        $this->registry->create('cashier', 'Cashier');

        $matched = $this->registry->lookup(null, ['admin', 'editor']);

        self::assertSame(['admin'], $matched->codes());
    }

    public function test_find_returns_the_role_for_a_known_code(): void
    {
        $this->registry->create('admin', 'Administrator');

        $role = $this->registry->find(null, 'admin');

        self::assertNotNull($role);
        self::assertSame('Administrator', $role->name());
    }

    public function test_find_returns_null_when_code_is_unknown(): void
    {
        $this->registry->create('admin', 'Administrator');

        self::assertNull($this->registry->find(null, 'editor'));
    }

    public function test_create_preserves_the_resolved_scope_and_lookup_is_exact(): void
    {
        $scopeA = new TestSubject(1);
        $scopeB = new TestSubject(2);
        $scopeC = new TestSubject(3);

        $roleA  = $this->registry->create('administrator', 'Administrator A', scope: $scopeA);
        $roleB  = $this->registry->create('administrator', 'Administrator B', scope: $scopeB);
        $global = $this->registry->create('auditor', 'Auditor');

        self::assertSame($scopeA, $roleA->scope());
        self::assertSame([$roleA], $this->registry->lookup($scopeA, ['administrator'])->values());
        self::assertSame([$roleB], $this->registry->lookup($scopeB, ['administrator'])->values());
        self::assertTrue($this->registry->lookup($scopeC, ['administrator'])->isEmpty());
        self::assertTrue($this->registry->lookup(null, ['administrator'])->isEmpty());
        self::assertSame([$global], $this->registry->lookup(null, ['auditor'])->values());
        self::assertTrue($this->registry->lookup($scopeA, ['auditor'])->isEmpty());
    }

    public function test_lookup_matches_scope_by_concrete_subject_class_and_normalized_identifier(): void
    {
        $identifier = new readonly class('organization-1') implements Identifier {
            public function __construct(
                private string $value,
            )
            {
            }

            public function value(): int|string
            {
                return $this->value;
            }

            public function __toString(): string
            {
                return $this->value;
            }
        };

        $scope = new TestSubject($identifier);
        $role  = $this->registry->create('administrator', 'Administrator', scope: $scope);

        self::assertSame([$role], $this->registry->lookup(new TestSubject('organization-1'), ['administrator'])->values());

        $differentSubjectClass = new readonly class('organization-1') implements Subject {
            public function __construct(
                private string $id,
            )
            {
            }

            public function id(): int|string|Identifier
            {
                return $this->id;
            }

            public function scope(): Subject|null
            {
                return null;
            }
        };

        self::assertTrue($this->registry->lookup($differentSubjectClass, ['administrator'])->isEmpty());
    }

    public function test_create_rejects_a_scoped_role_when_match_finds_a_global_role(): void
    {
        $scope      = new TestSubject(1);
        $repository = $this->createMock(RoleRepository::class);
        $repository->expects(self::once())
                   ->method('match')
                   ->with('administrator')
                   ->willReturn(new Roles([
                       new TestRole(1, 'administrator', 'Administrator'),
                   ]));
        $repository->expects(self::never())->method('create');

        $registry = new RoleRegistry($repository, $this->createStub(SubjectRoleRepository::class));

        try {
            $registry->create('administrator', 'Organization administrator', scope: $scope);
            self::fail('Expected local role creation to fail when a global role uses the code.');
        } catch (RoleAlreadyExists $exception) {
            self::assertSame(
                'Role [administrator] cannot be created as scoped because global and scoped roles share one namespace.',
                $exception->getMessage(),
            );
        }
    }

    public function test_create_rejects_a_global_role_when_match_finds_a_scoped_role(): void
    {
        $repository = $this->createMock(RoleRepository::class);
        $repository->expects(self::once())
                   ->method('match')
                   ->with('administrator')
                   ->willReturn(new Roles([
                       new TestRole(1, 'administrator', 'Organization administrator', scope: new TestSubject(1)),
                   ]));
        $repository->expects(self::never())->method('create');

        $registry = new RoleRegistry($repository, $this->createStub(SubjectRoleRepository::class));

        try {
            $registry->create('administrator', 'Administrator');
            self::fail('Expected global role creation to fail when a local role uses the code.');
        } catch (RoleAlreadyExists $exception) {
            self::assertSame(
                'Role [administrator] cannot be created as global because global and scoped roles share one namespace.',
                $exception->getMessage(),
            );
        }
    }

    public function test_create_rejects_a_scoped_role_when_match_finds_the_same_scope(): void
    {
        $scope      = new TestSubject('organization-1');
        $repository = $this->createMock(RoleRepository::class);
        $repository->expects(self::once())
                   ->method('match')
                   ->with('administrator')
                   ->willReturn(new Roles([
                       new TestRole(1, 'administrator', 'Organization administrator', scope: new TestSubject('organization-1')),
                   ]));
        $repository->expects(self::never())->method('create');

        $registry = new RoleRegistry($repository, $this->createStub(SubjectRoleRepository::class));

        try {
            $registry->create('administrator', 'Organization administrator', scope: $scope);
            self::fail('Expected duplicate local role creation to fail.');
        } catch (RoleAlreadyExists $exception) {
            self::assertSame(RoleAlreadyExists::fromCode('administrator')->getMessage(), $exception->getMessage());
        }
    }

    public function test_create_permits_a_scoped_role_when_match_finds_only_distinct_scopes(): void
    {
        $scope      = new TestSubject(1);
        $role       = new TestRole(2, 'administrator', 'Organization administrator', scope: $scope);
        $repository = $this->createMock(RoleRepository::class);
        $repository->expects(self::once())
                   ->method('match')
                   ->with('administrator')
                   ->willReturn(new Roles([
                       new TestRole(1, 'administrator', 'Other organization administrator', scope: new TestSubject(2)),
                   ]));
        $repository->expects(self::once())
                   ->method('create')
                   ->with('administrator', 'Organization administrator', null, $scope)
                   ->willReturn($role);

        $registry = new RoleRegistry($repository, $this->createStub(SubjectRoleRepository::class));

        self::assertSame($role, $registry->create('administrator', 'Organization administrator', scope: $scope));
    }
}
