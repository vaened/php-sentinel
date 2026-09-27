<?php

declare(strict_types=1);

/**
 * @author enea dhack <contact@vaened.dev>
 * @link https://vaened.dev DevFolio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vaened\Sentinel\Errors;

use Vaened\Sentinel\Identifiers;
use Vaened\Sentinel\Role;
use Vaened\Sentinel\Subject;

class InvalidAuthorization extends AuthorizationError
{
    public static function forRoleOwner(): static
    {
        return new static('A role can only receive permissions.');
    }

    public static function forRoleScope(Subject $subject, Role $role, Subject $scope): static
    {
        return new static(sprintf(
            'Role [%s] scoped to [%s:%s] cannot be assigned to subject [%s:%s].',
            $role->code(),
            $scope::class,
            Identifiers::value($scope->id()),
            $subject::class,
            Identifiers::value($subject->id()),
        ));
    }
}
