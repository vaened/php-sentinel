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

use Vaened\Sentinel\Subject;

final readonly class Authorizer
{
    public function __construct(
        protected PermissionEntryProvider $permissions,
        protected RoleEntryProvider       $roles,
    )
    {
    }

    public function can(Subject $subject, array $permissions, Junction $junction = Junction::Or): bool
    {
        $facts = $this->permissions->for($subject, ...$permissions);

        return $this->evaluate(
            $permissions,
            $junction,
            static fn(string $permission): bool => $facts->allows($permission)
        );
    }

    public function cannot(Subject $subject, array $permissions, Junction $junction = Junction::Or): bool
    {
        return !$this->can($subject, $permissions, $junction);
    }

    public function is(Subject $subject, array $roles, Junction $junction = Junction::Or): bool
    {
        $facts = $this->roles->for($subject, ...$roles);

        return $this->evaluate($roles, $junction, static fn(string $role): bool => $facts->has($role));
    }

    public function isnt(Subject $subject, array $roles, Junction $junction = Junction::Or): bool
    {
        return !$this->is($subject, $roles, $junction);
    }

    protected function evaluate(array $codes, Junction $junction, callable $predicate): bool
    {
        if (empty($codes)) {
            return false;
        }

        foreach ($codes as $code) {
            $matches = $predicate($code);

            if ($junction === Junction::Or && $matches) {
                return true;
            }

            if ($junction === Junction::And && !$matches) {
                return false;
            }
        }

        return $junction === Junction::And;
    }
}
