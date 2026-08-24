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

use Vaened\Sentinel\Authorization\Authorizer;
use Vaened\Sentinel\Authorization\PermissionEntryProvider;
use Vaened\Sentinel\Authorization\RoleEntryProvider;
use Vaened\Sentinel\Cache\CacheSettings;
use Vaened\Sentinel\Cache\SentinelCacheFactory;
use Vaened\Sentinel\Operators\Granter;
use Vaened\Sentinel\Operators\Revoker;
use Vaened\Sentinel\Tests\Runtime\InMemoryCache;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemoryPermissionRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemoryRolePermissionRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemoryRoleRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemorySubjectPermissionRepository;
use Vaened\Sentinel\Tests\Runtime\Repositories\InMemorySubjectRoleRepository;
use Vaened\Sentinel\Tests\Runtime\TestSubject;
use Vaened\Sentinel\Tests\TestCase;

final class CachedAuthorizerFlowTest extends TestCase
{
    public function test_subject_does_not_keep_a_role_permission_after_the_role_is_revoked(): void
    {
        $subjectPermissions = new InMemorySubjectPermissionRepository();
        $rolePermissions    = new InMemoryRolePermissionRepository();
        $subjectRoles       = new InMemorySubjectRoleRepository($rolePermissions);
        $roles              = new InMemoryRoleRepository();
        $permissions        = new InMemoryPermissionRepository();
        $cached             = SentinelCacheFactory::from(
            new InMemoryCache(),
            new CacheSettings(prefix: 'cached-authorizer-flow-test'),
        )->build(
            roles             : $roles,
            permissions       : $permissions,
            rolePermissions   : $rolePermissions,
            subjectRoles      : $subjectRoles,
            subjectPermissions: $subjectPermissions,
        );

        $granter    = new Granter(
            $cached->roleRepository(),
            $cached->permissionRepository(),
            $cached->subjectRoleRepository(),
            $cached->subjectPermissionRepository(),
            $cached->rolePermissionRepository(),
        );
        $revoker    = new Revoker(
            $cached->roleRepository(),
            $cached->permissionRepository(),
            $cached->subjectRoleRepository(),
            $cached->subjectPermissionRepository(),
            $cached->rolePermissionRepository(),
        );
        $authorizer = new Authorizer(
            new PermissionEntryProvider(
                $cached->subjectPermissionRepository(),
                $cached->subjectRoleRepository(),
            ),
            new RoleEntryProvider($cached->subjectRoleRepository()),
        );

        $subject    = new TestSubject(1);
        $role       = $cached->roleRepository()->create('cashier', 'Cashier');
        $permission = $cached->permissionRepository()->create('posts.edit', 'Edit Posts');

        $granter->grant($role, $permission);
        $granter->grant($subject, $role);
        $granter->grant($subject, $permission);

        $revoker->revoke($subject, $role);

        self::assertFalse($authorizer->can($subject, ['posts.edit']));
    }
}
