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

use Vaened\Sentinel\Authorizations;
use Vaened\Sentinel\Cache\SubjectAuthorizationProjectionCache;
use Vaened\Sentinel\Repositories\SubjectPermissionRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;
use Vaened\Sentinel\SubjectPermissions;

final class SubjectAuthorizationProjectionCacheTest extends CacheTestCase
{
    public function test_load_or_build_hits_the_source_repositories_only_once_per_subject(): void
    {
        $subject         = $this->cachedSubject();
        $role            = $this->cachedRole(10, 'cashier', 'Cashier');
        $createDocuments = $this->cachedPermission(20, 'documents.create', 'Create Documents');
        $annulDocuments  = $this->cachedSubjectPermission(21, 'documents.annul', true);

        $roles = $this->createMock(SubjectRoleRepository::class);
        $roles->expects(self::once())
              ->method('allOf')
              ->with($subject)
              ->willReturn(new Authorizations([$role]));
        $roles->expects(self::once())
              ->method('grants')
              ->with($subject)
              ->willReturn(new Authorizations([$createDocuments]));

        $permissions = $this->createMock(SubjectPermissionRepository::class);
        $permissions->expects(self::once())
                    ->method('allOf')
                    ->with($subject)
                    ->willReturn(new SubjectPermissions([$annulDocuments]));

        $cache = new SubjectAuthorizationProjectionCache(
            $this->cacheStore(),
            $roles,
            $permissions,
        );

        $first  = $cache->loadOrBuild($subject);
        $second = $cache->loadOrBuild($subject);

        self::assertSame(['cashier'], $first->roles()->codes());
        self::assertSame([
            'documents.annul'  => 0,
            'documents.create' => 2,
        ], $first->toArray()['permissions']);
        self::assertSame($first->toArray(), $second->toArray());
    }

}
