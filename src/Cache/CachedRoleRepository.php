<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Cache;

use Vaened\Sentinel\Authorizations;
use Vaened\Sentinel\Repositories\RoleRepository as RoleRepositoryContract;
use Vaened\Sentinel\Role;

final readonly class CachedRoleRepository implements RoleRepositoryContract
{
    public function __construct(
        private RoleRepositoryContract      $repository,
        private AuthorizationCacheStore    $cache,
    ) {
    }

    public function lookup(string ...$codes): Authorizations
    {
        return $this->repository->lookup(...$codes);
    }

    public function exists(int|string $id): bool
    {
        return $this->repository->exists($id);
    }

    public function create(string $code, string $name, string|null $description = null): Role
    {
        return $this->repository->create($code, $name, $description);
    }

    public function update(int|string $id, string $name, string|null $description = null): void
    {
        $this->repository->update($id, $name, $description);
    }

    public function remove(int|string $id): void
    {
        $this->repository->remove($id);
        $this->cache->invalidate();
    }
}
