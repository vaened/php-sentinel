<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Tests\Integration\Directory;

use Vaened\Sentinel\Directory\PermissionCreator;
use Vaened\Sentinel\Directory\PermissionRemover;
use Vaened\Sentinel\Directory\PermissionUpdater;
use Vaened\Sentinel\Errors\PermissionAlreadyExists;
use Vaened\Sentinel\Errors\PermissionInUse;
use Vaened\Sentinel\Errors\PermissionNotFound;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemoryPermissionRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemoryRolePermissionRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemorySubjectPermissionRepository;
use Vaened\Sentinel\Tests\Runtime\TestPermission;
use Vaened\Sentinel\Tests\Runtime\TestRole;
use Vaened\Sentinel\Tests\Runtime\TestSubject;
use Vaened\Sentinel\Tests\Runtime\TestSubjectPermission;
use Vaened\Sentinel\Tests\TestCase;

final class PermissionDirectoryTest extends TestCase
{
    private InMemoryPermissionRepository        $permissions;
    private InMemorySubjectPermissionRepository $subjectPermissions;
    private InMemoryRolePermissionRepository    $rolePermissions;

    private PermissionCreator $creator;
    private PermissionUpdater $updater;
    private PermissionRemover $remover;

    protected function setUp(): void
    {
        parent::setUp();

        $this->permissions        = new InMemoryPermissionRepository();
        $this->subjectPermissions = new InMemorySubjectPermissionRepository();
        $this->rolePermissions    = new InMemoryRolePermissionRepository();

        $this->creator = new PermissionCreator($this->permissions);
        $this->updater = new PermissionUpdater($this->permissions);
        $this->remover = new PermissionRemover(
            $this->permissions,
            $this->subjectPermissions,
            $this->rolePermissions,
        );
    }

    public function test_create_returns_a_persisted_permission_with_provided_attributes(): void
    {
        $permission = $this->creator->create('users.delete', 'Delete Users', 'Can delete any user');

        self::assertSame('users.delete', $permission->code());
        self::assertSame('Delete Users', $permission->name());
        self::assertSame('Can delete any user', $permission->description());
        self::assertTrue($this->permissions->exists($permission->id()));
    }

    public function test_create_throws_when_code_already_exists(): void
    {
        $this->creator->create('users.delete', 'Delete Users');

        $this->expectException(PermissionAlreadyExists::class);
        $this->creator->create('users.delete', 'Other Name');
    }

    public function test_update_renames_existing_permission(): void
    {
        $permission = $this->creator->create('users.delete', 'Delete Users');

        $this->updater->update($permission->id(), 'Remove Users', 'Can remove any user');

        $reloaded = $this->permissions->lookup('users.delete')->find('users.delete');
        self::assertInstanceOf(TestPermission::class, $reloaded);
        self::assertSame('Remove Users', $reloaded->name());
        self::assertSame('Can remove any user', $reloaded->description());
    }

    public function test_update_can_clear_description(): void
    {
        $permission = $this->creator->create('users.delete', 'Delete Users', 'Has description');

        $this->updater->update($permission->id(), 'Delete Users', null);

        $reloaded = $this->permissions->lookup('users.delete')->find('users.delete');
        self::assertInstanceOf(TestPermission::class, $reloaded);
        self::assertNull($reloaded->description());
    }

    public function test_update_throws_when_id_is_missing(): void
    {
        $this->expectException(PermissionNotFound::class);
        $this->updater->update(999, 'Other Name');
    }

    public function test_remove_silently_ignores_unknown_id(): void
    {
        $this->remover->remove(999);

        $this->expectNotToPerformAssertions();
    }

    public function test_remove_throws_when_a_subject_owns_the_permission(): void
    {
        $subject = new TestSubject(1);

        $permission = $this->creator->create('users.delete', 'Delete Users');
        $this->subjectPermissions->create($subject, TestSubjectPermission::fromPermission($permission));

        $this->expectException(PermissionInUse::class);
        $this->remover->remove($permission->id());
    }

    public function test_remove_throws_when_a_role_owns_the_permission(): void
    {
        $role = new TestRole(10, 'admin', 'Admin');

        $permission = $this->creator->create('users.delete', 'Delete Users');
        $this->rolePermissions->create($role, $permission);

        $this->expectException(PermissionInUse::class);
        $this->remover->remove($permission->id());
    }

    public function test_remove_succeeds_when_no_one_owns_it(): void
    {
        $permission = $this->creator->create('users.delete', 'Delete Users');

        $this->remover->remove($permission->id());

        self::assertFalse($this->permissions->exists($permission->id()));
    }
}
