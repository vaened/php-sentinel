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
use Vaened\Sentinel\Registry\RoleRegistry;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemoryRoleRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemorySubjectRoleRepository;
use Vaened\Sentinel\Tests\Runtime\TestRole;
use Vaened\Sentinel\Tests\Runtime\TestSubject;
use Vaened\Sentinel\Tests\TestCase;

final class RoleRegistryTest extends TestCase
{
    private InMemoryRoleRepository       $roles;
    private InMemorySubjectRoleRepository $subjectRoles;

    private RoleRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roles        = new InMemoryRoleRepository();
        $this->subjectRoles = new InMemorySubjectRoleRepository();

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

        $this->expectException(RoleAlreadyExists::class);
        $this->registry->create('admin', 'Other Name');
    }

    public function test_update_renames_existing_role(): void
    {
        $role = $this->registry->create('admin', 'Administrator');

        $this->registry->update($role->id(), 'Owner', 'Full access to everything');

        $reloaded = $this->roles->lookup('admin')->find('admin');
        self::assertInstanceOf(TestRole::class, $reloaded);
        self::assertSame('Owner', $reloaded->name());
        self::assertSame('Full access to everything', $reloaded->description());
    }

    public function test_update_can_clear_description(): void
    {
        $role = $this->registry->create('admin', 'Administrator', 'Full access');

        $this->registry->update($role->id(), 'Administrator', null);

        $reloaded = $this->roles->lookup('admin')->find('admin');
        self::assertInstanceOf(TestRole::class, $reloaded);
        self::assertNull($reloaded->description());
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
}
