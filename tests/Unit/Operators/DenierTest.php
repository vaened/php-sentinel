<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Tests\Unit\Operators;

use Vaened\Sentinel\Operators\Denier;
use Vaened\Sentinel\Permissions;
use Vaened\Sentinel\Repositories\PermissionRepository;
use Vaened\Sentinel\Repositories\RoleRepository;
use Vaened\Sentinel\Repositories\SubjectPermissionRepository;
use Vaened\Sentinel\Subject;
use Vaened\Sentinel\SubjectPermission;
use Vaened\Sentinel\SubjectPermissions;
use Vaened\Sentinel\Tests\Runtime\TestPermission;
use Vaened\Sentinel\Tests\Runtime\TestSubject;
use Vaened\Sentinel\Tests\TestCase;

final class DenierTest extends TestCase
{
    public function test_deny_creates_when_no_prior_assignment(): void
    {
        $permission = new TestPermission(1, 'posts.edit', 'Edit Posts');

        $catalog = $this->createMock(PermissionRepository::class);
        $catalog->method('lookup')->willReturn(new Permissions([$permission]));

        $assignments = $this->createMock(SubjectPermissionRepository::class);
        $assignments->method('lookup')->willReturn(new SubjectPermissions([]));
        $assignments->expects(self::once())
                    ->method('create')
                    ->willReturnCallback(function (Subject $subject, SubjectPermission ...$permissions): void {
                        self::assertTrue($permissions[0]->isDenied());
                    });

        $denier = new Denier(
            $this->createMock(RoleRepository::class),
            $catalog,
            $assignments,
        );

        $denier->deny(new TestSubject(1), $permission);
    }
}
