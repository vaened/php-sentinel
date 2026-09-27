<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Tests\Runtime\Repositories;

use Vaened\Sentinel\Errors\RoleAlreadyExists;
use Vaened\Sentinel\Repositories\RoleRepository;
use Vaened\Sentinel\Roles;
use Vaened\Sentinel\Scopes;
use Vaened\Sentinel\Subject;
use Vaened\Sentinel\Tests\Runtime\AbstractAuthorization;
use Vaened\Sentinel\Tests\Runtime\TestRole;

final class InMemoryRoleRepository implements RoleRepository
{
    /**
     * @var array<int|string, TestRole>
     */
    protected array $items  = [];

    protected int   $nextId = 1;

    public function lookup(Subject|null $scope, string ...$codes): Roles
    {
        $codes = array_flip($codes);

        return new Roles(array_values(array_filter(
            $this->items,
            fn(TestRole $role): bool => isset($codes[$role->code()]) && $this->hasSameScope($role, $scope),
        )));
    }

    public function match(string ...$codes): Roles
    {
        $codes = array_flip($codes);

        return new Roles(array_values(array_filter(
            $this->items,
            static fn(TestRole $role): bool => isset($codes[$role->code()]),
        )));
    }

    public function exists(int|string $id): bool
    {
        return isset($this->items[$id]);
    }

    public function create(
        string       $code,
        string       $name,
        string|null  $description = null,
        Subject|null $scope = null,
    ): TestRole
    {
        foreach ($this->items as $role) {
            if ($role->code() !== $code) {
                continue;
            }

            if ($scope === null || $role->scope() === null || $this->hasSameScope($role, $scope)) {
                throw RoleAlreadyExists::fromCode($code);
            }
        }

        $role                     = new TestRole($this->nextId++, $code, $name, $description, $scope);
        $this->items[$role->id()] = $role;

        return $role;
    }

    public function update(int|string $id, string $name, string|null $description = null): void
    {
        $role = $this->items[$id] ?? null;

        if ($role instanceof AbstractAuthorization) {
            $role->rename($name);
            $role->describe($description);
        }
    }

    public function remove(int|string $id): void
    {
        unset($this->items[$id]);
    }

    private function hasSameScope(TestRole $role, Subject|null $scope): bool
    {
        $roleScope = $role->scope();

        if ($roleScope === null || $scope === null) {
            return $roleScope === $scope;
        }

        return Scopes::same($roleScope, $scope);
    }
}
