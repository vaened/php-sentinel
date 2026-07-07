<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Operators;

use Vaened\Sentinel\Authorization;
use Vaened\Sentinel\Errors\InvalidAuthorization;
use Vaened\Sentinel\Permission;
use Vaened\Sentinel\Permissions;
use Vaened\Sentinel\Role;
use Vaened\Sentinel\Roles;
use Vaened\Sentinel\Subject;

trait BindingOperator
{
    abstract protected function forRoles(Subject $owner, Roles $roles): void;

    abstract protected function forSubjectPermissions(Subject $owner, Permissions $permissions): void;

    abstract protected function forRolePermissions(Role $owner, Permissions $permissions): void;

    protected function bind(Subject|Role $owner, Authorization ...$authorizations): void
    {
        [$roles, $permissions] = $this->split(...$authorizations);

        if ($owner instanceof Role && !empty($roles)) {
            throw InvalidAuthorization::forRoleOwner();
        }

        if ($owner instanceof Subject && !empty($roles)) {
            $this->forRoles($owner, new Roles($roles));
        }

        if (empty($permissions)) {
            return;
        }

        $permissions = new Permissions($permissions);

        if ($owner instanceof Subject) {
            $this->forSubjectPermissions($owner, $permissions);
            return;
        }

        $this->forRolePermissions($owner, $permissions);
    }

    protected function split(Authorization ...$authorizations): array
    {
        $roles       = [];
        $permissions = [];

        foreach ($authorizations as $authorization) {
            if ($authorization instanceof Role) {
                $roles[] = $authorization;
                continue;
            }

            if ($authorization instanceof Permission) {
                $permissions[] = $authorization;
            }
        }

        return [$roles, $permissions];
    }
}
