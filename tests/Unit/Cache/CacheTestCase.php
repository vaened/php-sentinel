<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Tests\Unit\Cache;

use Vaened\Sentinel\Cache\AuthorizationCacheStore;
use Vaened\Sentinel\Cache\CacheSettings;
use Vaened\Sentinel\Cache\SubjectAuthorizationProjectionCache;
use Vaened\Sentinel\Operators\SubjectPermissionSnapshot;
use Vaened\Sentinel\Permission;
use Vaened\Sentinel\Repositories\SubjectPermissionRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;
use Vaened\Sentinel\Role;
use Vaened\Sentinel\Subject;
use Vaened\Sentinel\Tests\Runtime\InMemoryCache;
use Vaened\Sentinel\Tests\Runtime\TestPermission;
use Vaened\Sentinel\Tests\Runtime\TestRole;
use Vaened\Sentinel\Tests\Runtime\TestSubject;
use Vaened\Sentinel\Tests\TestCase;

abstract class CacheTestCase extends TestCase
{
    protected InMemoryCache           $store;

    protected AuthorizationCacheStore $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = new InMemoryCache();
        $this->cache = new AuthorizationCacheStore(
            $this->store,
            new CacheSettings(prefix: uniqid('authorization-test-', true)),
        );
    }

    protected function cacheStore(): AuthorizationCacheStore
    {
        return $this->cache;
    }

    protected function projectionCache(
        SubjectRoleRepository|null       $roles = null,
        SubjectPermissionRepository|null $permissions = null,
    ): SubjectAuthorizationProjectionCache
    {
        return new SubjectAuthorizationProjectionCache(
            $this->cache,
            $roles ?? $this->createStub(SubjectRoleRepository::class),
            $permissions ?? $this->createStub(SubjectPermissionRepository::class),
        );
    }

    protected function cachedSubject(int|string $id = 1): Subject
    {
        return new TestSubject($id);
    }

    protected function cachedRole(
        int|string  $id,
        string      $code,
        string      $name = 'Role',
        string|null $description = null,
    ): Role
    {
        return new TestRole($id, $code, $name, $description);
    }

    protected function cachedPermission(
        int|string  $id,
        string      $code,
        string      $name = 'Permission',
        string|null $description = null,
    ): Permission
    {
        return new TestPermission($id, $code, $name, $description);
    }

    protected function cachedSubjectPermission(
        int|string $id,
        string     $code,
        bool       $isDenied = false,
    ): SubjectPermissionSnapshot
    {
        return new SubjectPermissionSnapshot($id, $code, $isDenied);
    }
}
