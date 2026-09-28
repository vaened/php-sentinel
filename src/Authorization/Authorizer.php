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

use Vaened\Sentinel\Propagation\ScopePropagationPolicy;
use Vaened\Sentinel\Propagation\TransitiveScopePropagationPolicy;
use Vaened\Sentinel\Subject;

final readonly class Authorizer
{
    public function __construct(
        protected PermissionEntryProvider $permissions,
        protected RoleEntryProvider       $roles,
        protected ScopePropagationPolicy  $propagation = new TransitiveScopePropagationPolicy(),
    )
    {
    }

    public function can(Subject $subject, array $permissions, Junction $junction = Junction::Or): bool
    {
        if (empty($permissions)) {
            return false;
        }

        $facts = [
            $this->permissions->for($subject, ...$permissions),
        ];

        foreach ($this->propagation->scopes($subject) as $scope) {
            $facts[] = $this->permissions->for($scope, ...$permissions);
        }

        return $this->evaluate(
            $permissions,
            $junction,
            static function (string $permission) use ($facts): bool {
                foreach ($facts as $entries) {
                    if (!$entries->allows($permission)) {
                        return false;
                    }
                }

                return true;
            },
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
