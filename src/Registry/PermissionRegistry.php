<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Registry;

use Vaened\Sentinel\Errors\PermissionAlreadyExists;
use Vaened\Sentinel\Errors\PermissionInUse;
use Vaened\Sentinel\Errors\PermissionNotFound;
use Vaened\Sentinel\Permission;
use Vaened\Sentinel\Permissions;
use Vaened\Sentinel\Repositories\PermissionRepository;
use Vaened\Sentinel\Repositories\RolePermissionRepository;
use Vaened\Sentinel\Repositories\SubjectPermissionRepository;

final readonly class PermissionRegistry
{
    public function __construct(
        protected PermissionRepository        $permissions,
        protected SubjectPermissionRepository $subjects,
        protected RolePermissionRepository    $roles,
    )
    {
    }

    public function create(string $code, string $name, string|null $description = null): Permission
    {
        if (!$this->permissions->lookup($code)->isEmpty()) {
            throw PermissionAlreadyExists::fromCode($code);
        }

        return $this->permissions->create($code, $name, $description);
    }

    public function update(int|string $id, string $name, string|null $description = null): void
    {
        if (!$this->permissions->exists($id)) {
            throw PermissionNotFound::fromId($id);
        }

        $this->permissions->update($id, $name, $description);
    }

    public function remove(int|string $id): void
    {
        if (!$this->permissions->exists($id)) {
            return;
        }

        if ($this->subjects->exists($id) || $this->roles->exists($id)) {
            throw PermissionInUse::fromId($id);
        }

        $this->permissions->remove($id);
    }

    public function lookup(array $codes): Permissions
    {
        return $this->permissions->lookup(...$codes);
    }

    public function find(string $code): Permission|null
    {
        return $this->permissions->lookup($code)->find($code);
    }
}
