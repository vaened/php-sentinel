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
use Vaened\Sentinel\Repositories\SubjectPermissionRepository;
use Vaened\Sentinel\Subject;
use Vaened\Sentinel\SubjectPermission;
use Vaened\Sentinel\SubjectPermissions;
use Vaened\Sentinel\Tests\Runtime\TestSubjectPermission;

final class InMemorySubjectPermissionRepository implements SubjectPermissionRepository
{
    /**
     * @var array<int|string, array<string, SubjectPermission>>
     */
    protected array $items = [];

    public function lookup(Subject $subject, string ...$codes): SubjectPermissions
    {
        $assigned = $this->items[Identifiers::value($subject->id())] ?? [];
        $codes    = array_flip($codes);

        return new SubjectPermissions(array_values(array_filter(
            $assigned,
            static fn(SubjectPermission $permission): bool => isset($codes[$permission->code()]),
        )));
    }

    public function exists(int|string $permissionId): bool
    {
        return array_any($this->items,
            fn($permissions) => array_any($permissions, fn($permission) => $permission->permissionId() === $permissionId));
    }

    public function allOf(Subject $subject): SubjectPermissions
    {
        return new SubjectPermissions(array_values($this->items[Identifiers::value($subject->id())] ?? []));
    }

    public function create(Subject $subject, SubjectPermission ...$permissions): void
    {
        foreach ($permissions as $permission) {
            $this->items[Identifiers::value($subject->id())][$permission->code()] = $permission instanceof TestSubjectPermission
                ? $permission
                : new TestSubjectPermission($permission->permissionId(), $permission->code(), $permission->isDenied());
        }
    }

    public function update(Subject $subject, SubjectPermission ...$permissions): void
    {
        foreach ($permissions as $permission) {
            $this->items[Identifiers::value($subject->id())][$permission->code()] = $permission instanceof TestSubjectPermission
                ? $permission
                : new TestSubjectPermission($permission->permissionId(), $permission->code(), $permission->isDenied());
        }
    }

    public function remove(Subject $subject, SubjectPermission ...$permissions): void
    {
        foreach ($permissions as $permission) {
            unset($this->items[Identifiers::value($subject->id())][$permission->code()]);
        }
    }
}
