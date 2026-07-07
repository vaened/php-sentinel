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

class RoleNotFound extends AuthorizationError
{
    public static function fromId(int|string $id): static
    {
        return new static(sprintf(
            'Role [%s] was not found.',
            $id,
        ));
    }

    public static function fromCodes(array $codes): static
    {
        return new static(sprintf(
            'Roles [%s] were not found.',
            implode(', ', $codes),
        ));
    }
}
