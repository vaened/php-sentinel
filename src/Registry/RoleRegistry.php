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

use Vaened\Sentinel\Errors\RoleAlreadyExists;
use Vaened\Sentinel\Errors\RoleInUse;
use Vaened\Sentinel\Errors\RoleNotFound;
use Vaened\Sentinel\Repositories\RoleRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;
use Vaened\Sentinel\Role;
use Vaened\Sentinel\Roles;

final readonly class RoleRegistry
{
    public function __construct(
        protected RoleRepository        $roles,
        protected SubjectRoleRepository $subjects,
    )
    {
    }

    public function create(string $code, string $name, string|null $description = null): Role
    {
        if (!$this->roles->lookup($code)->isEmpty()) {
            throw RoleAlreadyExists::fromCode($code);
        }

        return $this->roles->create($code, $name, $description);
    }

    public function update(int|string $id, string $name, string|null $description = null): void
    {
        if (!$this->roles->exists($id)) {
            throw RoleNotFound::fromId($id);
        }

        $this->roles->update($id, $name, $description);
    }

    public function remove(int|string $id): void
    {
        if (!$this->roles->exists($id)) {
            return;
        }

        if ($this->subjects->exists($id)) {
            throw RoleInUse::fromId($id);
        }

        $this->roles->remove($id);
    }

    public function lookup(array $codes): Roles
    {
        return $this->roles->lookup(...$codes);
    }

    public function find(string $code): Role|null
    {
        return $this->roles->lookup($code)->find($code);
    }
}
