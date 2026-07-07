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

use Vaened\Sentinel\Repositories\RoleRepository;
use Vaened\Sentinel\Roles;
use Vaened\Sentinel\Tests\Runtime\AbstractAuthorization;
use Vaened\Sentinel\Tests\Runtime\TestRole;

final class InMemoryRoleRepository implements RoleRepository
{
    /**
     * @var array<int|string, TestRole>
     */
    protected array $items = [];

    protected int $nextId = 1;

    public function lookup(string ...$codes): Roles
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

    public function create(string $code, string $name, string|null $description = null): TestRole
    {
        $role = new TestRole($this->nextId++, $code, $name, $description);
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
}
