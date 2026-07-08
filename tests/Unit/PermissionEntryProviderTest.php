<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Tests\Unit;

use Vaened\Sentinel\Authorization\PermissionEntryProvider;
use Vaened\Sentinel\Permissions;
use Vaened\Sentinel\Repositories\SubjectPermissionRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;
use Vaened\Sentinel\SubjectPermissions;
use Vaened\Sentinel\Tests\Runtime\TestPermission;
use Vaened\Sentinel\Tests\Runtime\TestSubject;
use Vaened\Sentinel\Tests\Runtime\TestSubjectPermission;
use Vaened\Sentinel\Tests\TestCase;

final class PermissionEntryProviderTest extends TestCase
{
    private SubjectPermissionRepository $subjectPermissions;

    private SubjectRoleRepository       $subjectRoles;

    private PermissionEntryProvider     $provider;

    private TestSubject                 $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subjectPermissions = $this->createMock(SubjectPermissionRepository::class);
        $this->subjectRoles       = $this->createMock(SubjectRoleRepository::class);

        $this->provider = new PermissionEntryProvider(
            $this->subjectPermissions,
            $this->subjectRoles,
        );

        $this->subject = new TestSubject(1);
    }

    public function test_returns_empty_entries_when_no_codes_are_requested(): void
    {
        $entries = $this->provider->for($this->subject);

        self::assertCount(0, $entries);
    }

    public function test_creates_entries_from_direct_permissions_preserving_the_denied_flag(): void
    {
        $allowed = new TestPermission(1, 'posts.edit', 'Edit Posts');
        $denied  = new TestPermission(2, 'posts.delete', 'Delete Posts');

        $this->subjectPermissions->method('lookup')
                                 ->willReturn(new SubjectPermissions([
                                     TestSubjectPermission::from($allowed),
                                     TestSubjectPermission::from($denied, true),
                                 ]));

        $entries = $this->provider->for($this->subject, 'posts.edit', 'posts.delete');

        self::assertCount(2, $entries);
        self::assertTrue($entries->allows('posts.edit'));
        self::assertFalse($entries->allows('posts.delete'));
    }

    public function test_falls_back_to_role_grants_for_codes_not_in_direct_permissions(): void
    {
        $permission = new TestPermission(1, 'posts.edit', 'Edit Posts');

        $this->subjectPermissions->method('lookup')
                                 ->willReturn(new SubjectPermissions([]));

        $this->subjectRoles->method('grants')
                           ->willReturn(new Permissions([$permission]));

        $entries = $this->provider->for($this->subject, 'posts.edit');

        self::assertCount(1, $entries);
        self::assertTrue($entries->allows('posts.edit'));
    }

    public function test_direct_denial_overrides_role_grant_for_the_same_code(): void
    {
        $permission = new TestPermission(1, 'posts.edit', 'Edit Posts');

        $this->subjectPermissions->method('lookup')
                                 ->willReturn(new SubjectPermissions([
                                     TestSubjectPermission::from($permission, true),
                                 ]));

        $this->subjectRoles->method('grants')
                           ->willReturn(new Permissions([$permission]));

        $entries = $this->provider->for($this->subject, 'posts.edit');

        self::assertCount(1, $entries);
        self::assertFalse($entries->allows('posts.edit'));
    }

    public function test_direct_allow_keeps_allowed_flag_even_when_role_grants_same_code(): void
    {
        $permission = new TestPermission(1, 'posts.edit', 'Edit Posts');

        $this->subjectPermissions->method('lookup')
                                 ->willReturn(new SubjectPermissions([
                                     TestSubjectPermission::from($permission),
                                 ]));

        $this->subjectRoles->method('grants')
                           ->willReturn(new Permissions([$permission]));

        $entries = $this->provider->for($this->subject, 'posts.edit');

        self::assertCount(1, $entries);
        self::assertTrue($entries->allows('posts.edit'));
    }

    public function test_returns_empty_entries_when_neither_direct_nor_role_grants_have_the_codes(): void
    {
        $this->subjectPermissions->method('lookup')
                                 ->willReturn(new SubjectPermissions([]));

        $this->subjectRoles->method('grants')
                           ->willReturn(new Permissions([]));

        $entries = $this->provider->for($this->subject, 'posts.edit', 'posts.delete');

        self::assertCount(0, $entries);
    }
}
