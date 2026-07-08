<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Authorization;

use Vaened\Sentinel\Repositories\SubjectRoleRepository;
use Vaened\Sentinel\Role;
use Vaened\Sentinel\Subject;

final readonly class RoleEntryProvider
{
    public function __construct(
        protected SubjectRoleRepository $roles,
    ) {
    }

    public function for(Subject $subject, string ...$roles): RoleEntries
    {
        if ($roles === []) {
            return new RoleEntries([]);
        }

        return new RoleEntries(array_map(
            static fn(Role $role): RoleEntry => new RoleEntry($role->code()),
            $this->roles->lookup($subject, ...$roles)->values(),
        ));
    }
}
