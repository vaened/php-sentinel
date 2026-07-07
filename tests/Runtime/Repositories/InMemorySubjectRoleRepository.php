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

use Vaened\Sentinel\Identifiers;
use Vaened\Sentinel\Permissions;
use Vaened\Sentinel\Repositories\RolePermissionRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;
use Vaened\Sentinel\Role;
use Vaened\Sentinel\Roles;
use Vaened\Sentinel\Subject;

final class InMemorySubjectRoleRepository implements SubjectRoleRepository
{
    /**
     * @var array<int|string, array<string, Role>>
     */
    protected array $items = [];

    public function __construct(
        protected RolePermissionRepository $rolePermissions,
    ) {
    }

    public function lookup(Subject $subject, string ...$codes): Roles
    {
        $assigned = $this->items[Identifiers::value($subject->id())] ?? [];
        $codes    = array_flip($codes);

        return new Roles(array_values(array_filter(
            $assigned,
            static fn(Role $role): bool => isset($codes[$role->code()]),
        )));
    }

    public function grants(Subject $subject, string ...$codes): Permissions
    {
        $permissions = [];

        foreach ($this->allOf($subject) as $role) {
            foreach ($this->rolePermissions->lookup($role, ...$codes) as $permission) {
                $permissions[$permission->code()] = $permission;
            }
        }

        return new Permissions(array_values($permissions));
    }

    public function exists(int|string $roleId): bool
    {
        return array_any($this->items, fn($roles) => array_any($roles, fn($role) => $role->id() === $roleId));
    }

    public function allOf(Subject $subject): Roles
    {
        return new Roles(array_values($this->items[Identifiers::value($subject->id())] ?? []));
    }

    public function create(Subject $subject, Role ...$roles): void
    {
        foreach ($roles as $role) {
            $this->items[Identifiers::value($subject->id())][$role->code()] = $role;
        }
    }

    public function remove(Subject $subject, Role ...$roles): void
    {
        foreach ($roles as $role) {
            unset($this->items[Identifiers::value($subject->id())][$role->code()]);
        }
    }
}
