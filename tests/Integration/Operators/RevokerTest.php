<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Tests\Integration\Operators;

use Vaened\Sentinel\Operators\Granter;
use Vaened\Sentinel\Operators\Revoker;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemoryPermissionRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemoryRolePermissionRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemoryRoleRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemorySubjectPermissionRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemorySubjectRoleRepository;
use Vaened\Sentinel\Tests\Runtime\TestSubject;
use Vaened\Sentinel\Tests\TestCase;

final class RevokerTest extends TestCase
{
    public function test_revoke_removes_a_scoped_role_from_a_subject_in_the_same_scope(): void
    {
        $scope        = new TestSubject(2);
        $subject      = new TestSubject(1, $scope);
        $roles        = new InMemoryRoleRepository();
        $role         = $roles->create('editor', 'Editor', scope: $scope);
        $rolePerms    = new InMemoryRolePermissionRepository();
        $subjectRoles = new InMemorySubjectRoleRepository($rolePerms);
        $permissions  = new InMemoryPermissionRepository();
        $granter      = new Granter(
            $roles,
            $permissions,
            $subjectRoles,
            new InMemorySubjectPermissionRepository(),
            $rolePerms,
        );
        $revoker      = new Revoker(
            $roles,
            $permissions,
            $subjectRoles,
            new InMemorySubjectPermissionRepository(),
            $rolePerms,
        );

        $granter->grant($subject, $role);
        $revoker->revoke($subject, $role);

        self::assertFalse($subjectRoles->lookup($subject, 'editor')->hasCode('editor'));
    }
}
