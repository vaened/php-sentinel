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

use Vaened\Sentinel\Errors\RoleInUse;
use Vaened\Sentinel\Repositories\RoleRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;

final readonly class RoleRemover
{
    public function __construct(
        protected RoleRepository $roles,
        protected SubjectRoleRepository $subjects,
    ) {
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
}
