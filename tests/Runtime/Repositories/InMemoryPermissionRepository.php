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

use Vaened\Sentinel\Permissions;
use Vaened\Sentinel\Repositories\PermissionRepository;
use Vaened\Sentinel\Tests\Runtime\AbstractAuthorization;
use Vaened\Sentinel\Tests\Runtime\TestPermission;

final class InMemoryPermissionRepository implements PermissionRepository
{
    /**
     * @var array<int|string, TestPermission>
     */
    protected array $items = [];

    protected int $nextId = 1;

    public function lookup(string ...$codes): Permissions
    {
        $codes = array_flip($codes);

        return new Permissions(array_values(array_filter(
            $this->items,
            static fn(TestPermission $permission): bool => isset($codes[$permission->code()]),
        )));
    }

    public function exists(int|string $id): bool
    {
        return isset($this->items[$id]);
    }

    public function create(string $code, string $name, string|null $description = null): TestPermission
    {
        $permission = new TestPermission($this->nextId++, $code, $name, $description);
        $this->items[$permission->id()] = $permission;

        return $permission;
    }

    public function update(int|string $id, string $name, string|null $description = null): void
    {
        $permission = $this->items[$id] ?? null;

        if ($permission instanceof AbstractAuthorization) {
            $permission->rename($name);
            $permission->describe($description);
        }
    }

    public function remove(int|string $id): void
    {
        unset($this->items[$id]);
    }
}
