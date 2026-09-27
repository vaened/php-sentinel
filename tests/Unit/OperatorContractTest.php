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

use Vaened\Sentinel\Operators\Denier;
use Vaened\Sentinel\Operators\Revoker;
use Vaened\Sentinel\Permissions;
use Vaened\Sentinel\Repositories\PermissionRepository;
use Vaened\Sentinel\Repositories\RolePermissionRepository;
use Vaened\Sentinel\Repositories\RoleRepository;
use Vaened\Sentinel\Repositories\SubjectPermissionRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;
use Vaened\Sentinel\Subject;
use Vaened\Sentinel\SubjectPermission;
use Vaened\Sentinel\SubjectPermissions;
use Vaened\Sentinel\Tests\Runtime\TestPermission;
use Vaened\Sentinel\Tests\Runtime\TestSubject;
use Vaened\Sentinel\Tests\TestCase;

final class OperatorContractTest extends TestCase
{
    public function test_revoke_skips_remove_when_not_assigned(): void
    {
        $permission = new TestPermission(1, 'posts.edit', 'Edit Posts');

        $permissionRepository = $this->createMock(PermissionRepository::class);
        $permissionRepository->method('lookup')
                             ->willReturn(new Permissions([$permission]));

        $subjectPermissionRepository = $this->createMock(SubjectPermissionRepository::class);
        $subjectPermissionRepository->method('lookup')
                                    ->willReturn(new SubjectPermissions([]));

        $subjectPermissionRepository->expects($this->never())
                                    ->method('remove');

        $revoker = new Revoker(
            $this->createMock(RoleRepository::class),
            $permissionRepository,
            $this->createMock(SubjectRoleRepository::class),
            $subjectPermissionRepository,
            $this->createMock(RolePermissionRepository::class),
        );

        $revoker->revoke(new TestSubject(1), $permission);
    }

    public function test_deny_creates_when_no_prior_assignment(): void
    {
        $permission = new TestPermission(1, 'posts.edit', 'Edit Posts');

        $permissionRepository = $this->createMock(PermissionRepository::class);
        $permissionRepository->method('lookup')
                             ->willReturn(new Permissions([$permission]));

        $subjectPermissionRepository = $this->createMock(SubjectPermissionRepository::class);
        $subjectPermissionRepository->method('lookup')
                                    ->willReturn(new SubjectPermissions([]));

        $subjectPermissionRepository->expects($this->once())
                                    ->method('create')
                                    ->willReturnCallback(function (Subject $subject, SubjectPermission ...$permissions): void {
                                        self::assertTrue($permissions[0]->isDenied());
                                    });

        $denier = new Denier(
            $this->createMock(RoleRepository::class),
            $permissionRepository,
            $subjectPermissionRepository,
        );

        $denier->deny(new TestSubject(1), $permission);
    }

    public function test_purge_removes_all_subject_authorizations_without_catalog_lookups(): void
    {
        $subject = new TestSubject(1);

        $subjectRoles = $this->createMock(SubjectRoleRepository::class);
        $subjectRoles->expects($this->once())
                     ->method('purge')
                     ->with($subject);

        $subjectPermissions = $this->createMock(SubjectPermissionRepository::class);
        $subjectPermissions->expects($this->once())
                           ->method('purge')
                           ->with($subject);

        $revoker = new Revoker(
            $this->createMock(RoleRepository::class),
            $this->createMock(PermissionRepository::class),
            $subjectRoles,
            $subjectPermissions,
            $this->createMock(RolePermissionRepository::class),
        );

        $revoker->purge($subject);
    }
}
