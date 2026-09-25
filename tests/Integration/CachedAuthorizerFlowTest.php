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
use Vaened\Sentinel\Cache\CachedRepositories;
use Vaened\Sentinel\Cache\CacheSettings;
use Vaened\Sentinel\Cache\SentinelCacheFactory;
use Vaened\Sentinel\Cache\Stores\Psr16AuthorizationCacheStore;
use Vaened\Sentinel\Operators\Denier;
use Vaened\Sentinel\Operators\Granter;
use Vaened\Sentinel\Operators\Revoker;
use Vaened\Sentinel\Permission;
use Vaened\Sentinel\Role;
use Vaened\Sentinel\SubjectPermissionState;
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
    private Authorizer                          $authorizer;

    private Granter                             $granter;

    private Denier                              $denier;

    private Revoker                             $revoker;

    private CachedRepositories                  $repositories;

    private Psr16AuthorizationCacheStore        $cache;

    private InMemorySubjectPermissionRepository $subjectPermissions;

    private TestSubject                         $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subjectPermissions = new InMemorySubjectPermissionRepository();
        $rolePermissions          = new InMemoryRolePermissionRepository();
        $subjectRoles             = new InMemorySubjectRoleRepository($rolePermissions);
        $roles                    = new InMemoryRoleRepository();
        $permissions              = new InMemoryPermissionRepository();
        $this->cache              = new Psr16AuthorizationCacheStore(
            new InMemoryCache(),
            new CacheSettings(prefix: 'cached-authorizer-flow-test'),
        );
        $this->repositories       = SentinelCacheFactory::as($this->cache)->build(
            roles             : $roles,
            permissions       : $permissions,
            rolePermissions   : $rolePermissions,
            subjectRoles      : $subjectRoles,
            subjectPermissions: $this->subjectPermissions,
        );

        $this->granter    = new Granter(
            $this->repositories->roleRepository(),
            $this->repositories->permissionRepository(),
            $this->repositories->subjectRoleRepository(),
            $this->repositories->subjectPermissionRepository(),
            $this->repositories->rolePermissionRepository(),
        );
        $this->denier     = new Denier(
            $this->repositories->roleRepository(),
            $this->repositories->permissionRepository(),
            $this->repositories->subjectPermissionRepository(),
        );
        $this->revoker    = new Revoker(
            $this->repositories->roleRepository(),
            $this->repositories->permissionRepository(),
            $this->repositories->subjectRoleRepository(),
            $this->repositories->subjectPermissionRepository(),
            $this->repositories->rolePermissionRepository(),
        );
        $this->authorizer = new Authorizer(
            new PermissionEntryProvider(
                $this->repositories->subjectPermissionRepository(),
                $this->repositories->subjectRoleRepository(),
            ),
            new RoleEntryProvider($this->repositories->subjectRoleRepository()),
        );

        $this->subject = new TestSubject(1);
    }

    public function test_inherited_permission_is_neither_revoked_nor_granted_directly(): void
    {
        [$role, $permission] = $this->grantPermissionThroughRole();

        self::assertTrue($this->authorizer->can($this->subject, [$permission->code()]));
        $this->assertProjectionState($permission, SubjectPermissionState::Inherited);

        $this->revoker->revoke($this->subject, $permission);
        $this->assertPersistedState($permission, null);
        self::assertTrue($this->authorizer->can($this->subject, [$permission->code()]));

        $this->granter->grant($this->subject, $permission);
        $this->assertPersistedState($permission, null);

        $this->revoker->revoke($this->subject, $role);

        self::assertFalse($this->authorizer->can($this->subject, [$permission->code()]));
    }

    public function test_grant_replaces_an_inherited_denial_without_losing_the_role_grant(): void
    {
        [$role, $permission] = $this->grantPermissionThroughRole();

        $this->denier->deny($this->subject, $permission);

        self::assertFalse($this->authorizer->can($this->subject, [$permission->code()]));
        $this->assertPersistedState($permission, SubjectPermissionState::Denied);
        $this->assertProjectionState($permission, SubjectPermissionState::DeniedInherited);

        $this->granter->grant($this->subject, $permission);

        self::assertTrue($this->authorizer->can($this->subject, [$permission->code()]));
        $this->assertPersistedState($permission, SubjectPermissionState::Direct);
        $this->assertProjectionState($permission, SubjectPermissionState::DirectInherited);

        $this->revoker->revoke($this->subject, $role);

        self::assertTrue($this->authorizer->can($this->subject, [$permission->code()]));
        $this->assertProjectionState($permission, SubjectPermissionState::Direct);
    }

    public function test_deny_updates_a_direct_permission_that_is_also_inherited(): void
    {
        [$role, $permission] = $this->createRolePermission();

        $this->granter->grant($this->subject, $permission);
        $this->granter->grant($this->subject, $role);

        $this->assertPersistedState($permission, SubjectPermissionState::Direct);
        $this->assertProjectionState($permission, SubjectPermissionState::DirectInherited);

        $this->denier->deny($this->subject, $permission);

        self::assertFalse($this->authorizer->can($this->subject, [$permission->code()]));
        $this->assertPersistedState($permission, SubjectPermissionState::Denied);
        $this->assertProjectionState($permission, SubjectPermissionState::DeniedInherited);

        $this->revoker->revoke($this->subject, $permission);

        self::assertTrue($this->authorizer->can($this->subject, [$permission->code()]));
        $this->assertPersistedState($permission, null);
        $this->assertProjectionState($permission, SubjectPermissionState::Inherited);
    }

    public function test_revoking_a_direct_assignment_preserves_the_role_permission(): void
    {
        [$role, $permission] = $this->createRolePermission();

        $this->granter->grant($this->subject, $permission);
        $this->granter->grant($this->subject, $role);
        $this->assertProjectionState($permission, SubjectPermissionState::DirectInherited);

        $this->revoker->revoke($this->subject, $permission);

        self::assertTrue($this->authorizer->can($this->subject, [$permission->code()]));
        $this->assertPersistedState($permission, null);
        $this->assertProjectionState($permission, SubjectPermissionState::Inherited);
    }

    /**
     * @return array{Role, Permission}
     */
    private function grantPermissionThroughRole(): array
    {
        [$role, $permission] = $this->createRolePermission();

        $this->granter->grant($this->subject, $role);

        return [$role, $permission];
    }

    /**
     * @return array{Role, Permission}
     */
    private function createRolePermission(): array
    {
        $role       = $this->repositories->roleRepository()->create('cashier', 'Cashier');
        $permission = $this->repositories->permissionRepository()->create('posts.edit', 'Edit Posts');

        $this->granter->grant($role, $permission);

        return [$role, $permission];
    }

    private function assertPersistedState(Permission $permission, SubjectPermissionState|null $state): void
    {
        self::assertSame(
            $state,
            $this->subjectPermissions->lookup($this->subject, $permission->code())->find($permission->code())?->state(),
        );
    }

    private function assertProjectionState(Permission $permission, SubjectPermissionState $state): void
    {
        self::assertSame(
            $state,
            $this->cache->get($this->subject)?->permissions()->find($permission->code())?->state(),
        );
    }
}
