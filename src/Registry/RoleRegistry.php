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
use Vaened\Sentinel\Identifiers;
use Vaened\Sentinel\Repositories\RoleRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;
use Vaened\Sentinel\Role;
use Vaened\Sentinel\Roles;
use Vaened\Sentinel\Subject;

final readonly class RoleRegistry
{
    public function __construct(
        protected RoleRepository        $roles,
        protected SubjectRoleRepository $subjects,
    )
    {
    }

    public function create(
        string       $code,
        string       $name,
        string|null  $description = null,
        Subject|null $scope = null,
    ): Role
    {
        $this->ensureNoCollision($code, $scope);

        return $this->roles->create($code, $name, $description, $scope);
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

    public function lookup(Subject|null $scope, array $codes): Roles
    {
        return $this->roles->lookup($scope, ...$codes);
    }

    public function find(Subject|null $scope, string $code): Role|null
    {
        return $this->roles->lookup($scope, $code)->find($code);
    }

    private function ensureNoCollision(string $code, Subject|null $scope): void
    {
        foreach ($this->roles->match($code) as $role) {
            if (!self::collides($role, $scope)) {
                continue;
            }

            if (($scope === null) !== ($role->scope() === null)) {
                throw RoleAlreadyExists::fromScopeConflict($code, $scope === null);
            }

            throw RoleAlreadyExists::fromCode($code);
        }
    }

    private static function collides(Role $role, Subject|null $scope): bool
    {
        $roleScope = $role->scope();

        return $scope === null || $roleScope === null || self::hasSameScope($roleScope, $scope);
    }

    private static function hasSameScope(Subject $left, Subject $right): bool
    {
        return $left::class === $right::class
            && Identifiers::value($left->id()) === Identifiers::value($right->id());
    }
}
