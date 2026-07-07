<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Directory;

use Vaened\Sentinel\Errors\PermissionInUse;
use Vaened\Sentinel\Repositories\PermissionRepository;
use Vaened\Sentinel\Repositories\RolePermissionRepository;
use Vaened\Sentinel\Repositories\SubjectPermissionRepository;

final readonly class PermissionRemover
{
    public function __construct(
        protected PermissionRepository $permissions,
        protected SubjectPermissionRepository $subjects,
        protected RolePermissionRepository $roles,
    ) {
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
}
