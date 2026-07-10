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

use Vaened\Sentinel\Errors\PermissionAlreadyExists;
use Vaened\Sentinel\Errors\PermissionInUse;
use Vaened\Sentinel\Errors\PermissionNotFound;
use Vaened\Sentinel\Operators\SubjectPermissionSnapshot;
use Vaened\Sentinel\Registry\PermissionRegistry;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemoryPermissionRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemoryRolePermissionRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemorySubjectPermissionRepository;
use Vaened\Sentinel\Tests\Runtime\TestPermission;
use Vaened\Sentinel\Tests\Runtime\TestRole;
use Vaened\Sentinel\Tests\Runtime\TestSubject;
use Vaened\Sentinel\Tests\TestCase;

final class PermissionRegistryTest extends TestCase
{
    private InMemoryPermissionRepository        $permissions;

    private InMemorySubjectPermissionRepository $subjectPermissions;

    private InMemoryRolePermissionRepository    $rolePermissions;

    private PermissionRegistry                  $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->permissions        = new InMemoryPermissionRepository();
        $this->subjectPermissions = new InMemorySubjectPermissionRepository();
        $this->rolePermissions    = new InMemoryRolePermissionRepository();

        $this->registry = new PermissionRegistry(
            $this->permissions,
            $this->subjectPermissions,
            $this->rolePermissions,
        );
    }

    public function test_create_returns_a_persisted_permission_with_provided_attributes(): void
    {
        $permission = $this->registry->create('users.delete', 'Delete Users', 'Can delete any user');

        self::assertSame('users.delete', $permission->code());
        self::assertSame('Delete Users', $permission->name());
        self::assertSame('Can delete any user', $permission->description());
        self::assertTrue($this->permissions->exists($permission->id()));
    }

    public function test_create_throws_when_code_already_exists(): void
    {
        $this->registry->create('users.delete', 'Delete Users');

        $this->expectException(PermissionAlreadyExists::class);
        $this->registry->create('users.delete', 'Other Name');
    }

    public function test_update_renames_existing_permission(): void
    {
        $permission = $this->registry->create('users.delete', 'Delete Users');

        $this->registry->update($permission->id(), 'Remove Users', 'Can remove any user');

        $reloaded = $this->permissions->lookup('users.delete')->find('users.delete');
        self::assertInstanceOf(TestPermission::class, $reloaded);
        self::assertSame('Remove Users', $reloaded->name());
        self::assertSame('Can remove any user', $reloaded->description());
    }

    public function test_update_can_clear_description(): void
    {
        $permission = $this->registry->create('users.delete', 'Delete Users', 'Has description');

        $this->registry->update($permission->id(), 'Delete Users', null);

        $reloaded = $this->permissions->lookup('users.delete')->find('users.delete');
        self::assertInstanceOf(TestPermission::class, $reloaded);
        self::assertNull($reloaded->description());
    }

    public function test_update_throws_when_id_is_missing(): void
    {
        $this->expectException(PermissionNotFound::class);
        $this->registry->update(999, 'Other Name');
    }

    public function test_remove_silently_ignores_unknown_id(): void
    {
        $this->registry->remove(999);

        $this->expectNotToPerformAssertions();
    }

    public function test_remove_throws_when_a_subject_owns_the_permission(): void
    {
        $subject = new TestSubject(1);

        $permission = $this->registry->create('users.delete', 'Delete Users');
        $this->subjectPermissions->create($subject, SubjectPermissionSnapshot::from($permission));

        $this->expectException(PermissionInUse::class);
        $this->registry->remove($permission->id());
    }

    public function test_remove_throws_when_a_role_owns_the_permission(): void
    {
        $role = new TestRole(10, 'admin', 'Admin');

        $permission = $this->registry->create('users.delete', 'Delete Users');
        $this->rolePermissions->create($role, $permission);

        $this->expectException(PermissionInUse::class);
        $this->registry->remove($permission->id());
    }

    public function test_remove_succeeds_when_no_one_owns_it(): void
    {
        $permission = $this->registry->create('users.delete', 'Delete Users');

        $this->registry->remove($permission->id());

        self::assertFalse($this->permissions->exists($permission->id()));
    }

    public function test_lookup_delegates_to_the_permission_repository(): void
    {
        $this->registry->create('users.read', 'Read Users');
        $this->registry->create('users.write', 'Write Users');

        $matched = $this->registry->lookup(['users.read', 'users.delete']);

        self::assertSame(['users.read'], $matched->codes());
    }

    public function test_find_returns_the_permission_for_a_known_code(): void
    {
        $this->registry->create('users.read', 'Read Users');

        $permission = $this->registry->find('users.read');

        self::assertNotNull($permission);
        self::assertSame('Read Users', $permission->name());
    }

    public function test_find_returns_null_when_code_is_unknown(): void
    {
        $this->registry->create('users.read', 'Read Users');

        self::assertNull($this->registry->find('users.delete'));
    }
}
