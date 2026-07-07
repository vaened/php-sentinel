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

class PermissionInUse extends AuthorizationError
{
    public static function fromId(int|string $id): static
    {
        return new static(sprintf(
            'Permission [%s] is currently in use and cannot be removed.',
            $id,
        ));
    }
}
