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

use Vaened\Sentinel\Repositories\SubjectPermissionRepository;
use Vaened\Sentinel\Repositories\SubjectRoleRepository;
use Vaened\Sentinel\Subject;

final readonly class PermissionEntryProvider
{
    public function __construct(
        protected SubjectPermissionRepository $subjectPermissions,
        protected SubjectRoleRepository $subjectRoles,
    ) {
    }

    public function for(Subject $subject, string ...$permissions): PermissionEntries
    {
        if (empty($permissions)) {
            return new PermissionEntries([]);
        }

        $entries = [];

        foreach ($this->subjectPermissions->lookup($subject, ...$permissions) as $permission) {
            $entries[$permission->code()] = new PermissionEntry($permission->code(), $permission->isDenied());
        }

        $missing = array_values(array_filter(
            array_unique($permissions),
            static fn(string $code): bool => !isset($entries[$code]),
        ));

        if (empty($missing)) {
            return new PermissionEntries(array_values($entries));
        }

        foreach ($this->subjectRoles->grants($subject, ...$missing) as $permission) {
            $entries[$permission->code()] = new PermissionEntry($permission->code());
        }

        return new PermissionEntries(array_values($entries));
    }
}
